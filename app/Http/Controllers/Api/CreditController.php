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
    public function index()
    {
        // Gate::authorize('view-any-credit');
        $credits = Auth::user()->business->credits()->with('sale')->latest()->paginate(15);
        return response()->json($credits);
    }

    public function show(Credit $credit)
    {
        // Gate::authorize('view-credit', $credit);
        return $credit->load('sale');
    }

    public function addPayment(Request $request, Credit $credit)
    {
        // Gate::authorize('add-credit-payment', $credit);
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $credit->pending_amount,
        ]);

        DB::transaction(function () use ($credit, $validated) {
            $credit->increment('paid_amount', $validated['amount']);
            $credit->decrement('pending_amount', $validated['amount']);

            if ($credit->pending_amount <= 0) {
                $credit->update(['status' => 'paid']);
                $credit->sale()->update(['payment_status' => 'paid']);
            }
        });

        return response()->json($credit);
    }

    /**
     * Obtiene todos los créditos pendientes.
     */
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
