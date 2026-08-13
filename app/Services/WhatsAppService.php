<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    /**
     * Genera el mensaje formateado para una venta delivery.
     */
    public function formatSaleMessage(Sale $sale)
    {
        $sale->loadMissing('client');
        $client = $sale->client;

        $address = $this->shortenAddress($sale->delivery_address ?? $client->address);

        return "🚨 *NUEVO PEDIDO* 🚨\n\n"
             . "📍 Tienes un pedido para *{$address}*.\n\n"
             . "📲 Revisa el aplicativo para ver todos los detalles.";
    }

    /**
     * Limita una dirección a sus dos primeras secciones separadas por comas.
     */
    private function shortenAddress(?string $address): string
    {
        $parts = array_values(array_filter(
            array_map('trim', explode(',', (string) $address)),
            fn ($part) => $part !== ''
        ));

        return implode(', ', array_slice($parts, 0, 2));
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
