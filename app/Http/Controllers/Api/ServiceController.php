<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ServiceController extends Controller
{
    public function index()
    {
        // Gate::authorize('view-any-service');
        $services = Auth::user()->business->services()->with('category')->paginate(15);
        return response()->json($services);
    }

    public function store(Request $request)
    {
        // Gate::authorize('create-service');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'category_id' => 'nullable|exists:categories,id',
        ]);
        $service = Auth::user()->business->services()->create($validated);
        return response()->json($service, 201);
    }

    public function show(Service $service)
    {
        // Gate::authorize('view-service', $service);
        return $service->load('category');
    }

    public function update(Request $request, Service $service)
    {
        // Gate::authorize('update-service', $service);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'duration' => 'sometimes|required|integer|min:1',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'required|in:active,inactive',
        ]);
        $service->update($validated);
        return response()->json($service);
    }

    public function destroy(Service $service)
    {
        // Gate::authorize('delete-service', $service);
        $service->delete();
        return response()->json(null, 204);
    }
}
