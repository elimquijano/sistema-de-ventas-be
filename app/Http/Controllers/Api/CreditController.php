<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Credit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreditController extends Controller
{
    public function index(Request $request)
    {
        // Gate::authorize('view-any-credit');
        $query = Auth::user()->business->credits()->with('sale');

        // Filter by customer name or sale number
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('customer_name', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('sale', function ($saleQuery) use ($searchTerm) {
                        $saleQuery->where('sale_number', 'like', '%' . $searchTerm . '%');
                    });
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $credits = $query->latest()->paginate($request->get('per_page', 15));
        return response()->json($credits);
    }

    public function show(Credit $credit)
    {
        // Gate::authorize('view-credit', $credit);
        return $credit->load('sale');
    }

    public function update(Request $request, Credit $credit)
    {
        // Gate::authorize('update-credit', $credit);
        $validated = $request->validate([
            'due_date' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:pending,paid,overdue',
            'paid_amount' => 'sometimes|numeric|min:0|max:' . $credit->total_amount,
        ]);

        // Recalculate pending amount if paid_amount is changed
        if (isset($validated['paid_amount'])) {
            $validated['pending_amount'] = $credit->total_amount - $validated['paid_amount'];
            // Automatically update status based on new pending amount
            if ($validated['pending_amount'] <= 0) {
                $validated['status'] = 'paid';
            } else if ($credit->status === 'paid') {
                $validated['status'] = 'pending';
            }
        }

        $credit->update($validated);

        // If credit is paid, ensure the related sale is also marked as paid.
        if ($credit->status === 'paid') {
            $credit->sale()->update(['payment_status' => 'paid']);
        } else {
            $credit->sale()->update(['payment_status' => 'pending']);
        }

        return response()->json($credit);
    }

    public function addPayment(Request $request, Credit $credit)
    {
        // Gate::authorize('add-credit-payment', $credit);
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $credit->pending_amount,
        ]);

        DB::transaction(function () use ($credit, $validated) {
            // REVERTIDO: Ya no se busca ni se actualiza la caja registradora aquí.
            // La lógica de caja se mantiene separada de los pagos de crédito.

            $credit->increment('paid_amount', $validated['amount']);
            $credit->decrement('pending_amount', $validated['amount']);

            if ($credit->pending_amount <= 0) {
                $credit->update(['status' => 'paid']);
                $credit->sale()->update(['payment_status' => 'paid']);
            }
        });

        return response()->json($credit);
    }

    public function getPending()
    {
        // Gate::authorize('view-any-credit');
        $credits = Auth::user()->business->credits()
            ->where('status', 'pending')
            ->with('sale')
            ->latest()
            ->paginate(15);
        return response()->json($credits);
    }
}
