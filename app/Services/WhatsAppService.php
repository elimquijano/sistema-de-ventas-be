<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function __construct(private readonly OrderNotificationFormatter $orderNotifications) {}

    /**
     * Genera el mensaje formateado para una venta delivery.
     */
    public function formatSaleMessage(Sale $sale)
    {
        return $this->orderNotifications->whatsAppBody($sale);
    }

    /**
     * Envía el mensaje a través de la API externa.
     */
    public function sendMessage($toPhone, $message)
    {
        // Limpiar el teléfono y asegurar el prefijo 51
        $cleanPhone = preg_replace('/[^0-9]/', '', $toPhone);
        if (! str_starts_with($cleanPhone, '51')) {
            $cleanPhone = '51'.$cleanPhone;
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

            Log::error("Error enviando WhatsApp a {$cleanPhone}: ".$response->body());

            return false;
        } catch (\Exception $e) {
            Log::error("Excepción al enviar WhatsApp a {$cleanPhone}: ".$e->getMessage());

            return false;
        }
    }
}
