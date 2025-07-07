<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ExpenseController extends Controller
{
    public function index()
    {
        // Gate::authorize('view-any-expense');
        $expenses = Auth::user()->business->expenses()->with('category')->latest('expense_date')->paginate(15);
        return response()->json($expenses);
    }

    public function store(Request $request)
    {
        // Gate::authorize('create-expense');
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'category_id' => 'required|exists:categories,id',
            'receipt_path' => 'nullable|string',
        ]);
        $expense = Auth::user()->business->expenses()->create($validated + ['created_by' => Auth::id()]);
        return response()->json($expense, 201);
    }

    public function show(Expense $expense)
    {
        // Gate::authorize('view-expense', $expense);
        return $expense->load('category');
    }

    public function update(Request $request, Expense $expense)
    {
        // Gate::authorize('update-expense', $expense);
        $validated = $request->validate([
            'description' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'expense_date' => 'sometimes|required|date',
            'category_id' => 'sometimes|required|exists:categories,id',
            'receipt_path' => 'nullable|string',
        ]);
        $expense->update($validated);
        return response()->json($expense);
    }

    public function destroy(Expense $expense)
    {
        // Gate::authorize('delete-expense', $expense);
        $expense->delete();
        return response()->json(null, 204);
    }

    /**
     * Obtiene gastos por una categoría específica.
     */
    public function getByCategory(Request $request, $categoryId)
    {
        // Gate::authorize('view-any-expense');
        $expenses = Auth::user()->business->expenses()
            ->where('category_id', $categoryId)
            ->with('category')
            ->latest('expense_date')
            ->paginate(15);
        return response()->json($expenses);
    }
}
