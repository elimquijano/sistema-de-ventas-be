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
        $user = Auth::user();
        $query = Credit::query()->with('sale');

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

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

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $credits = $query->latest()->paginate($perPage);

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
            $credit->sale()->update(['status' => 'completed']);
        } else {
            $credit->sale()->update(['status' => 'debt']);
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
            // Actualizamos los montos usando el modelo para disparar eventos
            $credit->paid_amount += $validated['amount'];
            $credit->pending_amount -= $validated['amount'];

            if ($credit->pending_amount <= 0) {
                $credit->status = 'paid';
            }
            
            $credit->save();

            // Sincronizar estado de la venta
            if ($credit->status === 'paid') {
                $credit->sale()->update(['status' => 'completed']);
            }
        });

        return response()->json($credit);
    }

    public function getPending(Request $request)
    {
        $user = Auth::user();
        $query = Credit::query()->where('status', 'pending')->with('sale');

        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $credits = $query->latest()->paginate($perPage);

        return response()->json($credits);
    }

    public function timeline(Credit $credit)
    {
        return response()->json($credit->getDeepTimeline());
    }
}
