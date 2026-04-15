<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Loan::query()->with('creator');

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        // Filter by description
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $loans = $query->latest()->paginate($perPage);

        return response()->json($loans);
    }

    public function store(Request $request)
    {
        // Gate::authorize('create-loan');
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'paid_amount' => 'nullable|numeric|min:0|lte:amount',
            'loan_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:loan_date',
        ]);

        $paidAmount = $validated['paid_amount'] ?? 0;
        $pendingAmount = $validated['amount'] - $paidAmount;

        $loan = Auth::user()->business->loans()->create([
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'loan_date' => $validated['loan_date'],
            'due_date' => $validated['due_date'],
            'created_by' => Auth::id(),
            'status' => $pendingAmount <= 0 ? 'paid' : 'pending',
        ]);

        return response()->json($loan->load('creator'), 201);
    }

    public function show(Loan $loan)
    {
        // Gate::authorize('view-loan', $loan);
        return $loan->load('creator');
    }

    public function update(Request $request, Loan $loan)
    {
        // Gate::authorize('update-loan', $loan);
        $validated = $request->validate([
            'description' => 'sometimes|required|string|max:255',
            'loan_date' => 'sometimes|required|date',
            'due_date' => 'nullable|date|after_or_equal:loan_date',
            'status' => 'sometimes|required|in:pending,paid,overdue',
        ]);

        $loan->update($validated);
        return response()->json($loan);
    }

    public function destroy(Loan $loan)
    {
        // Gate::authorize('delete-loan', $loan);
        $loan->delete();
        return response()->json(null, 204);
    }

    public function addPayment(Request $request, Loan $loan)
    {
        // Gate::authorize('update-loan', $loan);
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $loan->pending_amount,
        ]);

        DB::transaction(function () use ($loan, $validated) {
            $loan->paid_amount += $validated['amount'];
            $loan->pending_amount -= $validated['amount'];

            if ($loan->pending_amount <= 0) {
                $loan->status = 'paid';
            }
            
            $loan->save();
        });

        return response()->json($loan);
    }

    public function timeline(Loan $loan)
    {
        return response()->json(
            $loan->audits()
                ->with('user')
                ->latest()
                ->get()
        );
    }
}
