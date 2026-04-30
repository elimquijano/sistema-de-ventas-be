<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssetRequest;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $query = Asset::query()->where('business_id', Auth::user()->business_id);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $assets = $query->latest()->paginate($perPage);

        return response()->json($assets);
    }

    public function store(StoreAssetRequest $request)
    {
        $asset = Auth::user()->business->assets()->create($request->validated());
        return response()->json($asset, 201);
    }

    public function show(Asset $asset)
    {
        $this->authorizeBusiness($asset);
        return response()->json($asset);
    }

    public function update(StoreAssetRequest $request, Asset $asset)
    {
        $this->authorizeBusiness($asset);
        $asset->update($request->validated());
        return response()->json($asset);
    }

    public function destroy(Asset $asset)
    {
        $this->authorizeBusiness($asset);
        $asset->delete();
        return response()->json(null, 204);
    }

    public function timeline(Asset $asset)
    {
        $this->authorizeBusiness($asset);
        return response()->json(
            $asset->audits()
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
