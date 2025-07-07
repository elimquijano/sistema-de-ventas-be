<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CashRegisterController extends Controller
{
    public function current()
    {
        // Gate::authorize('view-any-cash-register');
        $openRegister = Auth::user()->business->cashRegisters()->where('status', 'open')->first();
        if ($openRegister) {
            return response()->json(['success' => true, 'data' => $openRegister]);
        }
        return response()->json(['success' => false, 'message' => 'No hay caja abierta'], 404);
    }

    public function store(Request $request)
    {
        // Gate::authorize('create-cash-register');
        $business = Auth::user()->business;
        if ($business->cashRegisters()->where('status', 'open')->exists()) {
            return response()->json(['message' => 'Ya existe una caja registradora abierta'], 409);
        }
        $validated = $request->validate([
            'initial_amount' => 'required|numeric|min:0',
            'currency' => 'required|string|in:PEN,USD',
        ]);
        $register = $business->cashRegisters()->create($validated + [
            'opened_at' => now(),
            'opened_by' => Auth::id(),
            'status' => 'open',
        ]);
        return response()->json($register, 201);
    }

    public function close(Request $request, CashRegister $cashRegister)
    {
        // Gate::authorize('update-cash-register', $cashRegister);
        $validated = $request->validate(['final_amount' => 'required|numeric|min:0']);
        $cashSales = $cashRegister->sales()->where('payment_method', 'cash')->sum('total_amount');
        $expectedAmount = $cashRegister->initial_amount + $cashSales;
        $cashRegister->update([
            'final_amount' => $validated['final_amount'],
            'expected_amount' => $expectedAmount,
            'difference' => $validated['final_amount'] - $expectedAmount,
            'closed_at' => now(),
            'closed_by' => Auth::id(),
            'status' => 'closed',
        ]);
        return response()->json($cashRegister);
    }

    public function report(CashRegister $cashRegister)
    {
        // Gate::authorize('view-cash-register', $cashRegister);
        return $cashRegister->load('sales.items');
    }
}
