<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CashRegisterController extends Controller
{
    public function index(Request $request)
    {
        $query = Auth::user()->business->cashRegisters()->with(['business', 'openedBy', 'closedBy']);

        // Filtro directo por fecha de apertura (YYYY-MM-DD)
        if ($request->has('opened_at')) {
            $query->whereDate('opened_at', $request->opened_at);
        }

        // Filtro directo por fecha de cierre (YYYY-MM-DD)
        if ($request->has('closed_at')) {
            $query->whereDate('closed_at', $request->closed_at);
        }

        // Filtro por estado
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filtro por usuario que abrió la caja
        if ($request->has('opened_by')) {
            $query->where('opened_by', $request->opened_by);
        }

        // Ordenar por fecha de apertura descendente
        $query->orderBy('opened_at', 'desc');

        return response()->json($query->paginate(10));
    }

    public function current(Request $request)
    {
        $targetUserId = $request->input('user_id', Auth::id());

        $openRegister = Auth::user()->business->cashRegisters()
            ->where('status', 'open')
            ->where('opened_by', $targetUserId)
            ->first();

        if ($openRegister) {
            // total_in_cash: Monto inicial + efectivo de ventas + ingresos manuales + cobranza de créditos
            $openRegister->total_in_cash = $openRegister->initial_amount + $openRegister->cash_sales_amount + $openRegister->manual_inflow + ($openRegister->credit_collections ?? 0);
            // El campo 'profit' ya vendrá en el objeto $openRegister automáticamente
            return response()->json(['success' => true, 'data' => $openRegister]);
        }
        return response()->json(['success' => false, 'message' => 'No hay caja abierta'], 404);
    }

    public function addInflow(Request $request, CashRegister $cashRegister)
    {
        if ($cashRegister->business_id !== Auth::user()->business_id) {
            return response()->json(['message' => 'No autorizado para esta caja.'], 403);
        }

        if ($cashRegister->status === 'closed') {
            return response()->json(['message' => 'No se puede inyectar dinero a una caja cerrada.'], 400);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:255'
        ]);

        $cashRegister->increment('manual_inflow', $validated['amount']);
        $cashRegister->increment('expected_amount', $validated['amount']);
        
        return response()->json([
            'success' => true,
            'message' => 'Monto inyectado correctamente',
            'data' => $cashRegister
        ]);
    }

    public function store(Request $request)
    {
        $business = Auth::user()->business;
        $targetUserId = $request->input('user_id', Auth::id());

        if ($business->cashRegisters()->where('status', 'open')->where('opened_by', $targetUserId)->exists()) {
            $msg = ($targetUserId == Auth::id()) ? 'Ya tienes una caja registradora abierta' : 'El usuario ya tiene una caja abierta';
            return response()->json(['message' => $msg], 409);
        }

        $validated = $request->validate([
            'initial_amount' => 'required|numeric|min:0',
            'currency' => 'required|string|in:PEN,USD',
            'user_id' => 'nullable|exists:users,id'
        ]);

        $register = $business->cashRegisters()->create([
            'initial_amount' => $validated['initial_amount'],
            'currency' => $validated['currency'],
            'opened_at' => now(),
            'opened_by' => $targetUserId,
            'status' => 'open',
            'cash_sales_amount' => 0,
            'expected_amount' => $validated['initial_amount'], 
        ]);
        return response()->json($register, 201);
    }

    public function close(Request $request, CashRegister $cashRegister)
    {
        if ($cashRegister->business_id !== Auth::user()->business_id) {
            return response()->json(['message' => 'No autorizado para esta caja.'], 403);
        }

        if ($cashRegister->status === 'closed') {
            return response()->json(['message' => 'Esta caja ya está cerrada.'], 400);
        }

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
        if ($cashRegister->business_id !== Auth::user()->business_id) {
            return response()->json(['message' => 'No autorizado para esta caja.'], 403);
        }

        // Cargar las ventas con sus ítems y pagos para el desglose, excluyendo las canceladas
        $cashRegister->load(['sales' => function ($query) {
            $query->where('status', '!=', 'cancelled')
                  ->with(['payments', 'items.item']);
        }]);

        // --- DESGLOSE PARA EL FRONTEND ---
        
        // A. Dinero Inicial (Sencillo)
        $initial = (float) $cashRegister->initial_amount;
        
        // B. Ventas del día pagadas en EFECTIVO (Directas)
        $cashSales = (float) $cashRegister->cash_sales_amount;
        
        // C. Cobranza de Créditos (Deudas pasadas cobradas hoy en efectivo)
        $creditCollections = (float) ($cashRegister->credit_collections ?? 0);
        
        // D. Inyecciones Manuales (Dinero metido extra)
        $manualInflow = (float) $cashRegister->manual_inflow;

        // --- 1. LIQUIDACIÓN DE EFECTIVO (Rendición de cuentas física) ---
        $cashRegister->report_cash_to_deliver = $initial + $cashSales + $creditCollections + $manualInflow;

        // --- 2. DESGLOSE DETALLADO (Para que veas de dónde sale cada sol) ---
        $cashRegister->breakdown = [
            'initial_amount' => $initial,
            'direct_cash_sales' => $cashSales,
            'credit_debt_collections' => $creditCollections,
            'manual_inflow' => $manualInflow,
            'total_physical_cash' => $cashRegister->report_cash_to_deliver
        ];

        // --- 3. VENTAS PURAS DEL DÍA (Para el Dashboard / Ingresos vs Gastos) ---
        // expected_amount acumula el total de las ventas (Efectivo + Yape + Tarjeta + Crédito generado hoy)
        // Le restamos el Inicial y el Manual Inflow porque NO son ventas.
        $cashRegister->report_total_sales = (float) ($cashRegister->expected_amount - $initial - $manualInflow);

        // --- 4. GANANCIA (Logística propia del negocio) ---
        $cashRegister->report_profit = (float) $cashRegister->profit;

        return response()->json($cashRegister);
    }
}
