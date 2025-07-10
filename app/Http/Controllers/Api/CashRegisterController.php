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
        $openRegister = Auth::user()->business->cashRegisters()->where('status', 'open')->first();
        if ($openRegister) {
            // total_in_cash: Monto inicial + efectivo de ventas
            $openRegister->total_in_cash = $openRegister->initial_amount + $openRegister->cash_sales_amount;
            return response()->json(['success' => true, 'data' => $openRegister]);
        }
        return response()->json(['success' => false, 'message' => 'No hay caja abierta'], 404);
    }

    public function store(Request $request)
    {
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
            'cash_sales_amount' => 0,
            'expected_amount' => $validated['initial_amount'], // expected_amount inicia con el monto inicial
        ]);
        return response()->json($register, 201);
    }

    public function close(Request $request, CashRegister $cashRegister)
    {
        $validated = $request->validate(['final_amount' => 'required|numeric|min:0']);

        // expected_amount ya está actualizado por las ventas
        $expectedAmount = $cashRegister->expected_amount;

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
        // Calcular report_current_cash (efectivo actual en caja)
        $cashRegister->report_current_cash = $cashRegister->initial_amount + $cashRegister->cash_sales_amount;

        // Calcular la diferencia para el reporte
        $cashRegister->report_difference = $cashRegister->report_current_cash - $cashRegister->expected_amount;

        // Asegurarse de cargar las ventas con sus ítems para el reporte
        return $cashRegister->load('sales.items');
    }
}
