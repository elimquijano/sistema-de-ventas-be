<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Category::query();

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        $perPage = $this->getPaginationSize($request, $query);
        $categories = $query->paginate($perPage);

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        // Gate::authorize('create-category');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:product,service,expense',
        ]);
        $category = Auth::user()->business->categories()->create($validated);
        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        // Gate::authorize('view-category', $category);
        return response()->json($category);
    }

    public function update(Request $request, Category $category)
    {
        // Gate::authorize('update-category', $category);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:product,service,expense',
        ]);
        $category->update($validated);
        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        // Gate::authorize('delete-category', $category);
        $category->delete();
        return response()->json(null, 204);
    }
}
