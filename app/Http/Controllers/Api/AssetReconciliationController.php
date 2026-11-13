<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetInventoryMovement;
use App\Models\AssetInventorySnapshot;
use App\Models\AssetReconciliationProductMapping;
use App\Models\Business;
use App\Models\Product;
use App\Services\AssetReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssetReconciliationController extends Controller
{
    public function products(Request $request, Asset $asset)
    {
        $this->authorizeAsset($asset);

        $mappings = AssetReconciliationProductMapping::query()
            ->where('business_id', $this->businessId())
            ->with('asset:id,name')
            ->get()
            ->keyBy('product_id');

        $query = Product::query()
            ->where('business_id', $this->businessId())
            ->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        return response()->json([
            'asset' => $asset,
            'products' => $query->get()->map(function (Product $product) use ($mappings, $asset) {
                $mapping = $mappings->get($product->id);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'selected' => (int) ($mapping?->asset_id ?? 0) === (int) $asset->id,
                    'units_per_sale' => (int) ($mapping?->units_per_sale ?? 1),
                    'mapped_asset' => $mapping?->asset ? [
                        'id' => $mapping->asset->id,
                        'name' => $mapping->asset->name,
                    ] : null,
                ];
            })->values(),
        ]);
    }

    public function updateProducts(Request $request, Asset $asset)
    {
        $this->authorizeAsset($asset);
        $validated = $request->validate([
            'products' => 'present|array',
            'products.*.product_id' => 'required|integer|distinct|exists:products,id',
            'products.*.units_per_sale' => 'sometimes|integer|min:1',
        ]);

        $productIds = collect($validated['products'])->pluck('product_id');
        $validProducts = Product::where('business_id', $this->businessId())
            ->whereIn('id', $productIds)
            ->count();

        if ($validProducts !== $productIds->count()) {
            return response()->json([
                'message' => 'Uno o mas productos no pertenecen al negocio.',
            ], 422);
        }

        DB::transaction(function () use ($asset, $validated, $productIds): void {
            AssetReconciliationProductMapping::where('business_id', $this->businessId())
                ->where('asset_id', $asset->id)
                ->when($productIds->isNotEmpty(), fn ($query) => $query->whereNotIn('product_id', $productIds))
                ->delete();

            if ($productIds->isEmpty()) {
                AssetReconciliationProductMapping::where('business_id', $this->businessId())
                    ->where('asset_id', $asset->id)
                    ->delete();

                return;
            }

            foreach ($validated['products'] as $product) {
                AssetReconciliationProductMapping::updateOrCreate(
                    [
                        'business_id' => $this->businessId(),
                        'product_id' => $product['product_id'],
                    ],
                    [
                        'asset_id' => $asset->id,
                        'units_per_sale' => $product['units_per_sale'] ?? 1,
                        'active' => true,
                    ]
                );
            }
        });

        return $this->products($request, $asset);
    }

    public function prepare(
        Request $request,
        Business $business,
        AssetReconciliationService $service
    ) {
        $this->authorizeBusiness($business);
        $validated = $request->validate([
            'counted_at' => 'nullable|date',
            'base_snapshot_id' => 'nullable|integer|exists:asset_inventory_snapshots,id',
            'asset_ids' => 'nullable|array',
            'asset_ids.*' => 'integer|distinct|exists:assets,id',
        ]);

        $countedAt = Carbon::parse($validated['counted_at'] ?? now());
        $assetIds = collect($validated['asset_ids'] ?? []);
        if ($assetIds->isNotEmpty() && $business->assets()->whereIn('id', $assetIds)->count() !== $assetIds->count()) {
            return response()->json(['message' => 'Uno o mas bienes no pertenecen al negocio.'], 422);
        }

        return response()->json($service->calculate(
            $business,
            $countedAt,
            baseSnapshotId: $validated['base_snapshot_id'] ?? null,
            assetIds: $validated['asset_ids'] ?? []
        ));
    }

    public function storeSnapshot(
        Request $request,
        Business $business,
        AssetReconciliationService $service
    ) {
        $this->authorizeBusiness($business);
        $validated = $request->validate([
            'counted_at' => 'required|date',
            'base_snapshot_id' => 'nullable|integer|exists:asset_inventory_snapshots,id',
            'confirm_unbalanced' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.asset_id' => 'required|integer|distinct|exists:assets,id',
            'items.*.full_quantity' => 'required|integer|min:0',
            'items.*.empty_quantity' => 'required|integer|min:0',
            'adjustments' => 'sometimes|array',
            'adjustments.*.asset_id' => 'required|integer|exists:assets,id',
            'adjustments.*.type' => ['required', Rule::in(['external_in', 'external_out', 'refill'])],
            'adjustments.*.condition' => 'nullable|required_if:adjustments.*.type,external_in,external_out|in:full,empty',
            'adjustments.*.quantity' => 'required|integer|min:1',
            'adjustments.*.counterparty' => 'nullable|string|max:255',
            'adjustments.*.notes' => 'nullable|string|max:500',
        ]);

        $assetIds = collect($validated['items'])->pluck('asset_id')
            ->merge(collect($validated['adjustments'] ?? [])->pluck('asset_id'))
            ->unique();
        if ($business->assets()->whereIn('id', $assetIds)->count() !== $assetIds->count()) {
            return response()->json(['message' => 'Uno o mas bienes no pertenecen al negocio.'], 422);
        }
        foreach ($validated['adjustments'] ?? [] as $adjustment) {
            if (in_array($adjustment['type'], ['external_in', 'external_out'], true)
                && (empty($adjustment['condition']) || empty($adjustment['counterparty']))) {
                return response()->json([
                    'message' => 'Los movimientos externos requieren condicion y contraparte.',
                ], 422);
            }
        }

        $countedAt = Carbon::parse($validated['counted_at']);
        $report = $service->calculate(
            $business,
            $countedAt,
            $validated['items'],
            $validated['adjustments'] ?? [],
            $validated['base_snapshot_id'] ?? null
        );

        $confirmed = (bool) ($validated['confirm_unbalanced'] ?? false);
        if ($report['requires_confirmation'] && ! $confirmed) {
            return response()->json([
                'message' => 'El conteo tiene descuadres. Confirme si desea guardarlo.',
                'requires_confirmation' => true,
                'report' => $report,
            ], 409);
        }
        if ($report['requires_confirmation'] && empty($validated['notes'])) {
            return response()->json([
                'message' => 'Indique una nota para guardar un conteo con descuadre.',
            ], 422);
        }

        $snapshot = DB::transaction(function () use ($business, $validated, $countedAt, $report, $confirmed) {
            foreach ($validated['adjustments'] ?? [] as $adjustment) {
                AssetInventoryMovement::create([
                    'business_id' => $business->id,
                    'asset_id' => $adjustment['asset_id'],
                    'type' => $adjustment['type'],
                    'condition' => $adjustment['condition'] ?? null,
                    'quantity' => $adjustment['quantity'],
                    'counterparty' => $adjustment['counterparty'] ?? null,
                    'occurred_at' => $countedAt,
                    'notes' => $adjustment['notes'] ?? null,
                    'created_by' => Auth::id(),
                ]);
            }

            $snapshot = AssetInventorySnapshot::create([
                'business_id' => $business->id,
                'base_snapshot_id' => $report['base_snapshot']['id'] ?? null,
                'counted_at' => $countedAt,
                'status' => $report['status'],
                'confirmed_unbalanced' => $confirmed && $report['requires_confirmation'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($report['items'] as $line) {
                $movements = $line['movements'];
                $snapshot->items()->create([
                    'asset_id' => $line['asset']['id'],
                    'full_quantity' => $line['counted']['full_quantity'],
                    'empty_quantity' => $line['counted']['empty_quantity'],
                    'expected_full_quantity' => $line['expected']['full_quantity'],
                    'expected_empty_quantity' => $line['expected']['empty_quantity'],
                    'expected_total_quantity' => $line['expected']['total_quantity'],
                    'full_difference' => $line['differences']['full'],
                    'empty_difference' => $line['differences']['empty'],
                    'total_difference' => $line['differences']['total'],
                    'sales_quantity' => $movements['sales'],
                    'loaned_out_quantity' => $movements['loaned_out'],
                    'returned_in_quantity' => $movements['returned_in'],
                    'refilled_quantity' => $movements['refilled'],
                    'external_in_full' => $movements['external_in_full'],
                    'external_in_empty' => $movements['external_in_empty'],
                    'external_out_full' => $movements['external_out_full'],
                    'external_out_empty' => $movements['external_out_empty'],
                    'status' => $line['status'],
                    'calculation' => $line,
                ]);
            }

            return $snapshot;
        });

        return response()->json([
            'message' => 'Conteo guardado correctamente.',
            'snapshot' => $snapshot->load(['items.asset', 'creator']),
            'report' => $report,
        ], 201);
    }

    public function snapshots(Request $request, Business $business)
    {
        $this->authorizeBusiness($business);
        $query = $business->assetInventorySnapshots()
            ->with(['creator', 'items.asset'])
            ->latest('counted_at');

        if ($request->filled('from')) {
            $query->where('counted_at', '>=', Carbon::parse($request->from)->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('counted_at', '<=', Carbon::parse($request->to)->endOfDay());
        }

        return response()->json($query->paginate(20));
    }

    public function showSnapshot(Business $business, AssetInventorySnapshot $snapshot)
    {
        $this->authorizeBusiness($business);
        abort_unless((int) $snapshot->business_id === (int) $business->id, 404);

        return response()->json($snapshot->load(['baseSnapshot', 'creator', 'items.asset']));
    }

    public function storeMovement(Request $request, Business $business)
    {
        $this->authorizeBusiness($business);
        $validated = $request->validate([
            'asset_id' => 'required|integer|exists:assets,id',
            'type' => ['required', Rule::in(['external_in', 'external_out', 'refill'])],
            'condition' => 'nullable|required_if:type,external_in,external_out|in:full,empty',
            'quantity' => 'required|integer|min:1',
            'counterparty' => 'nullable|required_if:type,external_in,external_out|string|max:255',
            'occurred_at' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);
        abort_unless($business->assets()->whereKey($validated['asset_id'])->exists(), 422);

        $movement = AssetInventoryMovement::create([
            ...$validated,
            'business_id' => $business->id,
            'created_by' => Auth::id(),
        ]);

        return response()->json($movement->load(['asset', 'creator']), 201);
    }

    public function assetFlow(Request $request, Business $business, Asset $asset)
    {
        $this->authorizeBusiness($business);
        abort_unless((int) $asset->business_id === (int) $business->id, 404);
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $movements = AssetInventoryMovement::query()
            ->where('business_id', $business->id)
            ->where('asset_id', $asset->id)
            ->whereIn('type', ['loan_out', 'loan_return'])
            ->whereBetween('occurred_at', [
                Carbon::parse($validated['from'])->startOfDay(),
                Carbon::parse($validated['to'])->endOfDay(),
            ])
            ->with(['assetLoan:id,borrower_name,notes', 'creator:id,first_name,last_name'])
            ->orderByDesc('occurred_at')
            ->get();

        $serialize = fn ($movement) => [
            'movement_id' => $movement->id,
            'loan_id' => $movement->asset_loan_id,
            'person' => $movement->assetLoan?->borrower_name ?? $movement->counterparty,
            'quantity' => (int) $movement->quantity,
            'date' => $movement->occurred_at->toIso8601String(),
            'notes' => $movement->notes ?? $movement->assetLoan?->notes,
            'registered_by' => $movement->creator?->full_name,
        ];

        $outgoing = $movements->where('type', 'loan_out')->map($serialize)->values();
        $incoming = $movements->where('type', 'loan_return')->map($serialize)->values();

        return response()->json([
            'asset' => ['id' => $asset->id, 'name' => $asset->name],
            'period' => ['from' => $validated['from'], 'to' => $validated['to']],
            'summary' => [
                'outgoing' => $outgoing->sum('quantity'),
                'incoming' => $incoming->sum('quantity'),
                'net_out' => $outgoing->sum('quantity') - $incoming->sum('quantity'),
            ],
            'outgoing' => $outgoing,
            'incoming' => $incoming,
        ]);
    }

    private function authorizeAsset(Asset $asset): void
    {
        abort_unless((int) $asset->business_id === $this->businessId(), 403);
    }

    private function authorizeBusiness(Business $business): void
    {
        abort_unless((int) $business->id === $this->businessId(), 403);
    }

    private function businessId(): int
    {
        return (int) Auth::user()->business_id;
    }
}
