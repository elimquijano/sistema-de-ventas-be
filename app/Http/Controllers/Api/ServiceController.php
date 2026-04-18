<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Service::query()->with('category');

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $perPage = $this->getPaginationSize($request, $query);
        $services = $query->latest()->paginate($perPage);

        return response()->json($services);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'category_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'status' => 'required|in:active,inactive',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = uniqid() . '.jpg';
            $path = "services/{$filename}";

            try {
                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                $image = $manager->read($file);
                $image->scale(width: 800); // Servicios suelen ser fotos más pequeñas
                $encoded = $image->toJpeg(80);
                \Illuminate\Support\Facades\Storage::disk('public')->put($path, (string) $encoded);
                $validated['image_path'] = $path;
            } catch (\Exception $e) {
                $validated['image_path'] = $file->store('services', 'public');
            }
        }

        unset($validated['image']);

        $service = Auth::user()->business->services()->create($validated);
        return response()->json($service, 201);
    }

    public function show(Service $service)
    {
        return $service->load('category');
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'duration' => 'sometimes|required|integer|min:1',
            'category_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'status' => 'sometimes|required|in:active,inactive',
        ]);

        if ($request->hasFile('image')) {
            if ($service->image_path) {
                Storage::disk('public')->delete($service->image_path);
            }
            
            $file = $request->file('image');
            $filename = uniqid() . '.jpg';
            $path = "services/{$filename}";

            try {
                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                $image = $manager->read($file);
                $image->scale(width: 800);
                $encoded = $image->toJpeg(80);
                Storage::disk('public')->put($path, (string) $encoded);
                $validated['image_path'] = $path;
            } catch (\Exception $e) {
                $validated['image_path'] = $file->store('services', 'public');
            }
        }

        unset($validated['image']);

        $service->update($validated);
        return response()->json($service);
    }

    public function destroy(Service $service)
    {
        if ($service->image_path) {
            Storage::disk('public')->delete($service->image_path);
        }
        $service->delete();
        return response()->json(null, 204);
    }
}
