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
    /**
     * Muestra una lista de préstamos.
     */
    public function index()
    {
        // Gate::authorize('view-any-loan');
        $loans = Auth::user()->business->loans()->with('creator')->latest()->paginate(15);
        return response()->json($loans);
    }

    /**
     * Almacena un nuevo préstamo.
     */
    public function store(Request $request)
    {
        // Gate::authorize('create-loan');
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'loan_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:loan_date',
        ]);
        $loan = Auth::user()->business->loans()->create($validated + [
            'created_by' => Auth::id(),
            'pending_amount' => $validated['amount'], // Inicialmente el monto pendiente es el total
            'status' => 'pending',
        ]);
        return response()->json($loan, 201);
    }

    /**
     * Muestra un préstamo específico.
     */
    public function show(Loan $loan)
    {
        // Gate::authorize('view-loan', $loan);
        return $loan->load('creator');
    }

    /**
     * Actualiza un préstamo específico.
     */
    public function update(Request $request, Loan $loan)
    {
        // Gate::authorize('update-loan', $loan);
        $validated = $request->validate([
            'description' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'loan_date' => 'sometimes|required|date',
            'due_date' => 'nullable|date|after_or_equal:loan_date',
            'status' => 'sometimes|required|in:pending,paid,overdue',
        ]);
        $loan->update($validated);
        return response()->json($loan);
    }

    /**
     * Elimina un préstamo específico.
     */
    public function destroy(Loan $loan)
    {
        // Gate::authorize('delete-loan', $loan);
        $loan->delete();
        return response()->json(null, 204);
    }

    /**
     * Marca un préstamo como devuelto (o registra un pago).
     */
    public function markAsReturned(Request $request, Loan $loan)
    {
        // Gate::authorize('update-loan', $loan); // Reutilizar permiso de actualización
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $loan->pending_amount,
        ]);

        DB::transaction(function () use ($loan, $validated) {
            $loan->increment('paid_amount', $validated['amount']);
            $loan->decrement('pending_amount', $validated['amount']);

            if ($loan->pending_amount <= 0) {
                $loan->update(['status' => 'paid']);
            }
        });

        return response()->json($loan);
    }

    /**
     * Obtiene todos los préstamos pendientes.
     */
    public function getPending()
    {
        // Gate::authorize('view-any-loan');
        $loans = Auth::user()->business->loans()
            ->where('status', 'pending')
            ->with('creator')
            ->latest()
            ->paginate(15);
        return response()->json($loans);
    }
}
