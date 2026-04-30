<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssetLoanRequest;
use App\Models\Asset;
use App\Models\AssetLoan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetLoanController extends Controller
{
    public function index(Request $request)
    {
        $query = AssetLoan::query()
            ->with(['asset', 'creator'])
            ->where('business_id', Auth::user()->business_id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $loans = $query->latest()->paginate($perPage);

        return response()->json($loans);
    }

    public function store(StoreAssetLoanRequest $request)
    {
        $validated = $request->validated();
        $asset = Asset::findOrFail($validated['asset_id']);

        if ($asset->available_quantity < $validated['quantity']) {
            return response()->json(['message' => 'No hay suficiente cantidad disponible del activo.'], 422);
        }

        $loan = DB::transaction(function () use ($validated, $asset) {
            $loan = AssetLoan::create(array_merge($validated, [
                'business_id' => Auth::user()->business_id,
                'created_by' => Auth::id(),
                'status' => 'loaned',
            ]));

            $asset->decrement('available_quantity', $validated['quantity']);

            return $loan;
        });

        return response()->json($loan->load(['asset', 'creator']), 201);
    }

    public function show(AssetLoan $assetLoan)
    {
        $this->authorizeBusiness($assetLoan);
        return response()->json($assetLoan->load(['asset', 'creator']));
    }

    public function returnAsset(Request $request, AssetLoan $assetLoan)
    {
        $this->authorizeBusiness($assetLoan);

        if ($assetLoan->status === 'returned') {
            return response()->json(['message' => 'Este préstamo ya fue devuelto.'], 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:returned,damaged,lost',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($assetLoan, $validated) {
            $assetLoan->update([
                'status' => $validated['status'],
                'return_date' => now(),
                'notes' => $validated['notes'] ?? $assetLoan->notes,
            ]);

            if ($validated['status'] === 'returned') {
                $assetLoan->asset->increment('available_quantity', $assetLoan->quantity);
            }
            // Si está perdido o dañado, no incrementamos el stock disponible a menos que se repare (otra lógica)
        });

        return response()->json($assetLoan->load(['asset']));
    }

    public function timeline(AssetLoan $assetLoan)
    {
        $this->authorizeBusiness($assetLoan);
        return response()->json(
            $assetLoan->audits()
                ->with('user')
                ->latest()
                ->get()
        );
    }

    protected function authorizeBusiness($model)
    {
        if ($model->business_id !== Auth::user()->business_id) {
            abort(403, 'No tienes permiso para acceder a este recurso.');
        }
    }
}
