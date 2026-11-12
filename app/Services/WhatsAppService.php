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

    /**
     * Entrega la petición HTTP al socket y cierra sin leer la respuesta del proveedor.
     * El resultado solo indica si se pudo escribir la petición, no si WhatsApp la entregó.
     */
    public function sendMessageWithoutWaiting(string $toPhone, string $message): bool
    {
        $socket = null;

        try {
            $url = parse_url(env('WHATSAPP_API_URL', 'http://109.123.240.188:3001/api/v1/messages/text'));
            if (! is_array($url) || ! isset($url['scheme'], $url['host'])
                || ! in_array($url['scheme'], ['http', 'https'], true)) {
                Log::error('No se pudo iniciar WhatsApp: URL de API inválida.');

                return false;
            }

            $scheme = $url['scheme'];
            $host = $url['host'];
            $defaultPort = $scheme === 'https' ? 443 : 80;
            $port = $url['port'] ?? $defaultPort;
            $transport = $scheme === 'https' ? 'tls' : 'tcp';
            $socket = @stream_socket_client("{$transport}://{$host}:{$port}", $errorCode, $errorMessage, 1);

            if ($socket === false) {
                Log::warning('No se pudo conectar con la API de WhatsApp.', [
                    'error_code' => $errorCode,
                    'error' => $errorMessage,
                ]);

                return false;
            }

            stream_set_timeout($socket, 1);

            $phone = preg_replace('/[^0-9]/', '', $toPhone);
            if (! str_starts_with($phone, '51')) {
                $phone = '51'.$phone;
            }

            $payload = json_encode(['recipient' => $phone, 'body' => $message], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $path = ($url['path'] ?? '/') . (isset($url['query']) ? '?'.$url['query'] : '');
            $hostHeader = $host.($port === $defaultPort ? '' : ':'.$port);
            $request = "POST {$path} HTTP/1.1\r\n"
                ."Host: {$hostHeader}\r\n"
                .'Authorization: Bearer '.env('WHATSAPP_API_TOKEN')."\r\n"
                ."Content-Type: application/json\r\n"
                .'Content-Length: '.strlen($payload)."\r\n"
                ."Connection: close\r\n\r\n"
                .$payload;

            $written = 0;
            while ($written < strlen($request)) {
                $bytes = @fwrite($socket, substr($request, $written));
                if ($bytes === false || $bytes === 0) {
                    Log::warning('No se pudo escribir la petición a la API de WhatsApp.');

                    return false;
                }

                $written += $bytes;
            }

            return true;
        } catch (\Throwable $exception) {
            Log::error('No se pudo iniciar la notificación por WhatsApp.', ['exception' => $exception]);

            return false;
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }
}
