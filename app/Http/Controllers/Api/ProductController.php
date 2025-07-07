<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index()
    {
        // Gate::authorize('view-any-product');
        $products = Auth::user()->business->products()->with('category')->paginate(15);
        return response()->json($products);
    }

    public function store(Request $request)
    {
        // Gate::authorize('create-product');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'barcode' => 'nullable|string|unique:products,barcode',
            'price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'image_path' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
        ]);
        $product = Auth::user()->business->products()->create($validated);
        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        // Gate::authorize('view-product', $product);
        return $product->load('category');
    }

    public function update(Request $request, Product $product)
    {
        // Gate::authorize('update-product', $product);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'barcode' => 'nullable|string|unique:products,barcode,' . $product->id,
            'price' => 'sometimes|required|numeric|min:0',
            'cost' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'min_stock' => 'sometimes|required|integer|min:0',
            'image_path' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
        ]);
        $product->update($validated);
        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        // Gate::authorize('delete-product', $product);
        $product->delete();
        return response()->json(null, 204);
    }

    /**
     * Actualiza el stock de un producto.
     */
    public function updateStock(Request $request, Product $product)
    {
        // Gate::authorize('update-product', $product); // Reutilizar permiso de actualización
        $validated = $request->validate([
            'stock' => 'required|integer|min:0',
        ]);
        $product->update(['stock' => $validated['stock']]);
        return response()->json($product);
    }

    /**
     * Obtiene productos con stock bajo.
     */
    public function getLowStock()
    {
        // Gate::authorize('view-any-product'); // Reutilizar permiso de vista
        $products = Auth::user()->business->products()
            ->whereColumn('stock', '<=', 'min_stock')
            ->with('category')
            ->paginate(15);
        return response()->json($products);
    }
}
