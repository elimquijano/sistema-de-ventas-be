<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage; // Import Storage facade

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Expense::query()->with(['category', 'creator']);

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        // Search by description
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $expenses = $query->latest('expense_date')->paginate($perPage);

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
            'receipt_path' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $expense = Auth::user()->business->expenses()->create($validated + ['created_by' => Auth::id()]);

        // Return the expense with the creator relationship loaded
        return response()->json($expense->load('creator', 'category'), 201);
    }

    public function show(Expense $expense)
    {
        // Gate::authorize('view-expense', $expense);
        return $expense->load(['category', 'creator']);
    }

    public function update(Request $request, Expense $expense)
    {
        // Gate::authorize('update-expense', $expense);
        $validated = $request->validate([
            'description' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'expense_date' => 'sometimes|required|date',
            'category_id' => 'sometimes|required|exists:categories,id',
            'receipt_path' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $expense->update($validated);

        // Return the expense with the creator relationship loaded
        return response()->json($expense->load('creator', 'category'));
    }

    public function destroy(Expense $expense)
    {
        // Gate::authorize('delete-expense', $expense);

        // Also delete the receipt file from storage if it exists
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }

        $expense->delete();
        return response()->json(null, 204);
    }

    /**
     * Obtiene gastos por una categoría específica.
     */
    public function getByCategory(Request $request, $categoryId)
    {
        $user = Auth::user();
        $query = Expense::query()
            ->where('category_id', $categoryId)
            ->with(['category', 'creator']);

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $expenses = $query->latest('expense_date')->paginate($perPage);

        return response()->json($expenses);
    }
}
