<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetInventoryMovement;
use App\Models\AssetInventorySnapshot;
use App\Models\AssetReconciliationProductMapping;
use App\Models\Business;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssetReconciliationService
{
    public function calculate(
        Business $business,
        Carbon $countedAt,
        array $items = [],
        array $adjustments = [],
        ?int $baseSnapshotId = null,
        array $assetIds = []
    ): array {
        $baseSnapshot = $this->resolveBaseSnapshot($business, $countedAt, $baseSnapshotId);
        $baseItems = $baseSnapshot
            ? $baseSnapshot->items()->get()->keyBy('asset_id')
            : collect();

        $submittedItems = collect($items)->keyBy('asset_id');
        $requestedAssetIds = collect($assetIds)
            ->merge($submittedItems->keys())
            ->filter()
            ->unique()
            ->values();

        $assetsQuery = $business->assets()->orderBy('name');
        if ($requestedAssetIds->isNotEmpty()) {
            $assetsQuery->whereIn('id', $requestedAssetIds);
        } else {
            $assetsQuery->where('status', 'active');
        }

        $assets = $assetsQuery->get();
        $startAt = $baseSnapshot?->counted_at;
        $adjustmentsByAsset = collect($adjustments)->groupBy('asset_id');
        $lines = $assets->map(function (Asset $asset) use (
            $business,
            $countedAt,
            $startAt,
            $baseItems,
            $submittedItems,
            $adjustmentsByAsset
        ) {
            $baseItem = $baseItems->get($asset->id);
            $submitted = $submittedItems->get($asset->id);
            $proposedAdjustments = $adjustmentsByAsset->get($asset->id, collect());
            $movementTotals = $this->movementTotals($business, $asset, $startAt, $countedAt, $proposedAdjustments);
            $sales = $baseItem
                ? $this->salesQuantity($business, $asset, $startAt, $countedAt)
                : 0;
            $mappings = AssetReconciliationProductMapping::query()
                ->where('business_id', $business->id)
                ->where('asset_id', $asset->id)
                ->where('active', true)
                ->with('product:id,name')
                ->get();

            $expectedFull = null;
            $expectedEmpty = null;
            if ($baseItem) {
                $expectedFull = (int) $baseItem->full_quantity
                    - $sales
                    + $movementTotals['refilled']
                    + $movementTotals['external_in_full']
                    - $movementTotals['external_out_full'];
                $expectedEmpty = (int) $baseItem->empty_quantity
                    + $sales
                    - $movementTotals['loaned_out']
                    + $movementTotals['returned_in']
                    - $movementTotals['refilled']
                    + $movementTotals['external_in_empty']
                    - $movementTotals['external_out_empty'];
            }

            $externalOutstanding = $this->externalOutstanding($business, $asset, $countedAt, $proposedAdjustments);
            $expectedTotal = $baseItem
                ? $expectedFull + $expectedEmpty
                : (int) $asset->available_quantity + $externalOutstanding;

            $fullQuantity = $submitted !== null ? (int) $submitted['full_quantity'] : null;
            $emptyQuantity = $submitted !== null ? (int) $submitted['empty_quantity'] : null;
            $actualTotal = $submitted !== null ? $fullQuantity + $emptyQuantity : null;
            $fullDifference = $expectedFull !== null && $fullQuantity !== null
                ? $fullQuantity - $expectedFull
                : null;
            $emptyDifference = $expectedEmpty !== null && $emptyQuantity !== null
                ? $emptyQuantity - $expectedEmpty
                : null;
            $totalDifference = $actualTotal !== null ? $actualTotal - $expectedTotal : null;
            $status = $this->lineStatus(
                $submitted !== null,
                $baseItem !== null,
                $fullDifference,
                $emptyDifference,
                $totalDifference
            );

            return [
                'asset' => [
                    'id' => $asset->id,
                    'name' => $asset->name,
                    'type' => $asset->type,
                    'total_quantity' => (int) $asset->total_quantity,
                    'available_quantity' => (int) $asset->available_quantity,
                ],
                'sales_tracking_enabled' => $mappings->isNotEmpty(),
                'mapped_products' => $mappings->map(fn ($mapping) => [
                    'id' => $mapping->product_id,
                    'name' => $mapping->product?->name,
                    'units_per_sale' => (int) $mapping->units_per_sale,
                ])->values(),
                'warning' => $mappings->isEmpty()
                    ? 'Este bien no tiene productos configurados para conciliacion.'
                    : null,
                'previous' => $baseItem ? [
                    'snapshot_id' => $baseItem->snapshot_id,
                    'full_quantity' => (int) $baseItem->full_quantity,
                    'empty_quantity' => (int) $baseItem->empty_quantity,
                    'total_quantity' => (int) $baseItem->full_quantity + (int) $baseItem->empty_quantity,
                ] : null,
                'movements' => [
                    'sales' => $sales,
                    ...$movementTotals,
                    'external_outstanding' => $externalOutstanding,
                ],
                'expected' => [
                    'full_quantity' => $expectedFull,
                    'empty_quantity' => $expectedEmpty,
                    'total_quantity' => $expectedTotal,
                ],
                'counted' => $submitted !== null ? [
                    'full_quantity' => $fullQuantity,
                    'empty_quantity' => $emptyQuantity,
                    'total_quantity' => $actualTotal,
                ] : null,
                'differences' => [
                    'full' => $fullDifference,
                    'empty' => $emptyDifference,
                    'total' => $totalDifference,
                ],
                'status' => $status,
            ];
        });

        $hasCountedItems = $submittedItems->isNotEmpty();
        $overallStatus = ! $hasCountedItems
            ? 'pending_count'
            : ($lines->every(fn ($line) => $line['status'] === 'balanced') ? 'balanced' : 'unbalanced');

        return [
            'counted_at' => $countedAt->toIso8601String(),
            'base_snapshot' => $baseSnapshot ? [
                'id' => $baseSnapshot->id,
                'counted_at' => $baseSnapshot->counted_at->toIso8601String(),
            ] : null,
            'period' => [
                'from' => $startAt?->toIso8601String(),
                'to' => $countedAt->toIso8601String(),
            ],
            'status' => $overallStatus,
            'requires_confirmation' => $overallStatus === 'unbalanced',
            'items' => $lines->values()->all(),
        ];
    }

    private function resolveBaseSnapshot(Business $business, Carbon $countedAt, ?int $baseSnapshotId): ?AssetInventorySnapshot
    {
        $query = $business->assetInventorySnapshots()->where('counted_at', '<', $countedAt);

        if ($baseSnapshotId) {
            return $query->whereKey($baseSnapshotId)->firstOrFail();
        }

        return $query->latest('counted_at')->first();
    }

    private function salesQuantity(Business $business, Asset $asset, ?Carbon $startAt, Carbon $endAt): int
    {
        if (! $startAt) {
            return 0;
        }

        return (int) DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('asset_reconciliation_product_mappings as mappings', function ($join) use ($business, $asset) {
                $join->on('sale_items.item_id', '=', 'mappings.product_id')
                    ->where('mappings.business_id', '=', $business->id)
                    ->where('mappings.asset_id', '=', $asset->id)
                    ->where('mappings.active', '=', true);
            })
            ->where('sale_items.item_type', Product::class)
            ->where('sales.business_id', $business->id)
            ->whereIn('sales.status', ['completed', 'debt'])
            ->where('sales.scheduled_at', '>', $startAt)
            ->where('sales.scheduled_at', '<=', $endAt)
            ->whereNull('sales.deleted_at')
            ->whereNull('sale_items.deleted_at')
            ->sum(DB::raw('sale_items.quantity * mappings.units_per_sale'));
    }

    private function movementTotals(
        Business $business,
        Asset $asset,
        ?Carbon $startAt,
        Carbon $endAt,
        Collection $proposedAdjustments
    ): array {
        $totals = [
            'loaned_out' => 0,
            'returned_in' => 0,
            'refilled' => 0,
            'external_in_full' => 0,
            'external_in_empty' => 0,
            'external_out_full' => 0,
            'external_out_empty' => 0,
            'damaged' => 0,
            'lost' => 0,
        ];

        if ($startAt) {
            $movements = AssetInventoryMovement::query()
                ->where('business_id', $business->id)
                ->where('asset_id', $asset->id)
                ->where('occurred_at', '>', $startAt)
                ->where('occurred_at', '<=', $endAt)
                ->selectRaw('type, `condition`, SUM(quantity) as total')
                ->groupBy('type', 'condition')
                ->get();

            foreach ($movements as $movement) {
                $this->addMovementToTotals($totals, $movement->type, $movement->condition, (int) $movement->total);
            }
        }

        foreach ($proposedAdjustments as $adjustment) {
            $this->addMovementToTotals(
                $totals,
                $adjustment['type'],
                $adjustment['condition'] ?? null,
                (int) $adjustment['quantity']
            );
        }

        return $totals;
    }

    private function externalOutstanding(
        Business $business,
        Asset $asset,
        Carbon $countedAt,
        Collection $proposedAdjustments
    ): int {
        $movements = AssetInventoryMovement::query()
            ->where('business_id', $business->id)
            ->where('asset_id', $asset->id)
            ->whereIn('type', ['external_in', 'external_out'])
            ->where('occurred_at', '<=', $countedAt)
            ->selectRaw('type, SUM(quantity) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $outstanding = (int) ($movements['external_in'] ?? 0) - (int) ($movements['external_out'] ?? 0);
        foreach ($proposedAdjustments as $adjustment) {
            if ($adjustment['type'] === 'external_in') {
                $outstanding += (int) $adjustment['quantity'];
            } elseif ($adjustment['type'] === 'external_out') {
                $outstanding -= (int) $adjustment['quantity'];
            }
        }

        return $outstanding;
    }

    private function addMovementToTotals(array &$totals, string $type, ?string $condition, int $quantity): void
    {
        $key = match ($type) {
            'loan_out' => 'loaned_out',
            'loan_return' => 'returned_in',
            'refill' => 'refilled',
            'external_in' => 'external_in_'.($condition ?? 'empty'),
            'external_out' => 'external_out_'.($condition ?? 'empty'),
            'damaged', 'lost' => $type,
            default => null,
        };

        if ($key && array_key_exists($key, $totals)) {
            $totals[$key] += $quantity;
        }
    }

    private function lineStatus(
        bool $hasCount,
        bool $hasBase,
        ?int $fullDifference,
        ?int $emptyDifference,
        ?int $totalDifference
    ): string {
        if (! $hasCount) {
            return 'pending_count';
        }

        if (! $hasBase) {
            return $totalDifference === 0 ? 'balanced' : 'total_mismatch';
        }

        if ($fullDifference === 0 && $emptyDifference === 0 && $totalDifference === 0) {
            return 'balanced';
        }

        if ($totalDifference === 0) {
            return 'composition_mismatch';
        }

        return 'total_mismatch';
    }
}
