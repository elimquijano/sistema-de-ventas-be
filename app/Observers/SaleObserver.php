<?php

namespace App\Observers;

use App\Models\Sale;

class SaleObserver
{
    /**
     * Handle the Sale "created" event.
     */
    public function created(Sale $sale): void
    {
        //
    }

    /**
     * Handle the Sale "updated" event.
     */
    /**
     * Handle the Sale "updated" event.
     *
     * @param  \App\Models\Sale  $sale
     * @return void
     */
    public function updated(Sale $sale): void
    {
        // Solo actuar si el estado de la venta ha cambiado.
        if (!$sale->wasChanged('status')) {
            return;
        }

        // Asegurarse de que la venta tenga un crédito asociado.
        if (!$credit = $sale->credit) {
            return;
        }

        // Caso 1: La venta se marca como 'completed'.
        // Sincroniza el crédito para que se marque como 'paid'.
        if ($sale->status === 'completed' && $credit->status !== 'paid') {
            $credit->status = 'paid';
            $credit->pending_amount = 0;
            $credit->paid_amount = $credit->total_amount;
            $credit->saveQuietly(); // Evita bucles de eventos.
        }
        // Caso 2: La venta se revierte a 'pending' desde 'completed'.
        // Sincroniza el crédito para que vuelva a estar 'pending'.
        elseif ($sale->status === 'pending' && $credit->status === 'paid') {
            $credit->status = 'pending';
            // Se asume que al revertir, el monto pagado vuelve a ser 0.
            // Esto podría necesitar una lógica de negocio más compleja si se manejan pagos parciales.
            $credit->pending_amount = $credit->total_amount;
            $credit->paid_amount = 0;
            $credit->saveQuietly(); // Evita bucles de eventos.
        }
    }

    /**
     * Handle the Sale "deleted" event.
     */
    public function deleted(Sale $sale): void
    {
        //
    }

    /**
     * Handle the Sale "restored" event.
     */
    public function restored(Sale $sale): void
    {
        //
    }

    /**
     * Handle the Sale "force deleted" event.
     */
    public function forceDeleted(Sale $sale): void
    {
        //
    }
}
