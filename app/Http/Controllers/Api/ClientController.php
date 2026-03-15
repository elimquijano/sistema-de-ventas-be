<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        // Filtrar por el negocio del usuario actual
        $query = Client::query()->where('business_id', $user->business_id);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('phone', 'like', '%' . $searchTerm . '%');
            });
        }

        if ($request->filled('phone')) {
            $query->where('phone', $request->phone);
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        $perPage = $request->get('per_page', 15);
        if ($perPage == -1) {
            $total = $query->count();
            $perPage = $total > 0 ? $total : 15;
        }

        $clients = $query->orderBy('name')->paginate($perPage);
        return response()->json($clients);
    }

    public function store(StoreClientRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('clients', 'public');
            $validated['image'] = $path;
        }
        
        // Handle route field if it is a JSON string (common in multipart/form-data)
        if (isset($validated['route']) && is_string($validated['route'])) {
            $decoded = json_decode($validated['route'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $validated['route'] = $decoded;
            }
        }

        $client = Client::create(array_merge($validated, [
            'business_id' => $user->business_id,
            'created_by_user_id' => $user->id,
        ]));

        return response()->json($client, 201);
    }

    public function show(Client $client)
    {
        $user = Auth::user();
        if ($client->business_id !== $user->business_id) {
            throw new AuthorizationException('This action is unauthorized.');
        }
        return response()->json($client);
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $user = Auth::user();
        if ($client->business_id !== $user->business_id) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $validated = $request->validated();

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($client->image) {
                Storage::disk('public')->delete($client->image);
            }
            $path = $request->file('image')->store('clients', 'public');
            $validated['image'] = $path. ''; // Ensure it's string
        }

        // Handle route field if it is a JSON string
        if (isset($validated['route']) && is_string($validated['route'])) {
            $decoded = json_decode($validated['route'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $validated['route'] = $decoded;
            }
        }

        $client->update($validated);
        
        return response()->json($client);
    }

    public function destroy(Client $client)
    {
        $user = Auth::user();
        if ($client->business_id !== $user->business_id) {
            throw new AuthorizationException('This action is unauthorized.');
        }
        
        $client->delete();
        return response()->json(null, 204);
    }
}
