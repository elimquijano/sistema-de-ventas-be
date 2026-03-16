<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Genera el mensaje formateado para una venta delivery.
     */
    public function formatSaleMessage(Sale $sale)
    {
        $sale->load(['items', 'client']);
        $client = $sale->client;
        
        // Asumiendo que sale_items tiene item_name y quantity
        $itemsText = "";
        foreach ($sale->items as $item) {
            $itemsText .= "📦 *Producto:* {$item->quantity} x {$item->item_name}\n";
        }

        $mapsLink = "https://www.google.com/maps?q={$client->latitude},{$client->longitude}";
        
        $message = "🛵 *NUEVO PEDIDO ASIGNADO* 🛵\n"
                 . "---------------------------\n"
                 . "👤 *Cliente:* {$client->name}\n"
                 . "📍 *Dirección:* " . ($sale->delivery_address ?? $client->address) . "\n"
                 . "📞 *Teléfono:* {$client->phone}\n"
                 . "🗺️ *Maps:* {$mapsLink}\n"
                 . $itemsText
                 . "💰 *Total a Cobrar:* S/ " . number_format($sale->total_amount, 2) . "\n"
                 . "📝 *Notas:* " . ($sale->delivery_notes ?? 'Sin notas') . "\n"
                 . "---------------------------\n"
                 . "Favor de confirmar al entregar.";
        
        return $message;
    }

    /**
     * Simula el envío (por ahora solo loguea).
     * Aquí se conectaría con una API real como Twilio, Meta API, o un Webhook.
     */
    public function sendMessage($toPhone, $message)
    {
        Log::info("Enviando WhatsApp a {$toPhone}:\n" . $message);
        
        // TODO: Implementar integración con proveedor de WhatsApp
        // Ejemplo: Http::post('...', ['to' => $toPhone, 'message' => $message]);
        
        return true;
    }
}
