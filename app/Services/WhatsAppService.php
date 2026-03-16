<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

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
     * Envía el mensaje a través de la API externa.
     */
    public function sendMessage($toPhone, $message)
    {
        // Limpiar el teléfono y asegurar el prefijo 51
        $cleanPhone = preg_replace('/[^0-9]/', '', $toPhone);
        if (!str_starts_with($cleanPhone, '51')) {
            $cleanPhone = '51' . $cleanPhone;
        }

        $apiUrl = env('WHATSAPP_API_URL', 'http://109.123.240.188:3001/api/v1/messages/text');
        $token = env('WHATSAPP_API_TOKEN');

        try {
            $response = Http::withToken($token)
                ->post($apiUrl, [
                    'recipient' => $cleanPhone,
                    'body' => $message,
                ]);

            if ($response->successful()) {
                Log::info("WhatsApp enviado exitosamente a {$cleanPhone}");
                return true;
            }

            Log::error("Error enviando WhatsApp a {$cleanPhone}: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("Excepción al enviar WhatsApp a {$cleanPhone}: " . $e->getMessage());
            return false;
        }
    }
}
