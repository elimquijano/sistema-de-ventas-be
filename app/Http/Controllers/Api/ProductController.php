<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Product::query()->with('category');

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $perPage = $this->getPaginationSize($request, $query);
        $products = $query->latest()->paginate($perPage);

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'barcode' => 'nullable|string|unique:products,barcode',
            'price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'required|in:active,inactive',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = uniqid() . '.jpg';
            $path = "products/{$filename}";

            try {
                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                $image = $manager->read($file);
                $image->scale(width: 800);
                $encoded = $image->toJpeg(80);
                Storage::disk('public')->put($path, (string) $encoded);
                $validated['image_path'] = $path;
            } catch (\Exception $e) {
                $validated['image_path'] = $file->store('products', 'public');
            }
        }

        unset($validated['image']);

        $product = Auth::user()->business->products()->create($validated);
        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return $product->load('category');
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'barcode' => 'nullable|string|unique:products,barcode,' . $product->id,
            'price' => 'sometimes|required|numeric|min:0',
            'cost' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'min_stock' => 'sometimes|required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'category_id' => 'nullable|exists:categories,id', // Corrected typo category__id -> category_id
            'status' => 'sometimes|required|in:active,inactive',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            
            $file = $request->file('image');
            $filename = uniqid() . '.jpg';
            $path = "products/{$filename}";

            try {
                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                $image = $manager->read($file);
                $image->scale(width: 800);
                $encoded = $image->toJpeg(80);
                Storage::disk('public')->put($path, (string) $encoded);
                $validated['image_path'] = $path;
            } catch (\Exception $e) {
                $validated['image_path'] = $file->store('products', 'public');
            }
        }

        unset($validated['image']);

        $product->update($validated);
        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }
        $product->delete();
        return response()->json(null, 204);
    }

    public function updateStock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'stock' => 'required|integer|min:0',
        ]);
        $product->update(['stock' => $validated['stock']]);
        return response()->json($product);
    }

    public function getLowStock(Request $request)
    {
        $user = Auth::user();
        $query = Product::query()
            ->whereColumn('stock', '<=', 'min_stock')
            ->with('category');

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    public function search(Request $request)
    {
        $user = Auth::user();
        $term = $request->query('term');
        $query = Product::query();

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        $products = $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('barcode', 'LIKE', "%{$term}%");
            })
            ->select('id', 'name', 'cost', 'price', 'stock')
            ->limit(10)
            ->get();
            
        return response()->json($products);
    }
}
