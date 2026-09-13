<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Reenvía el mensaje de una venta al rider asignado o a un teléfono específico.
     */
    public function resendSaleMessage(Request $request, Sale $sale)
    {
        $request->validate([
            'phone' => 'nullable|string|max:20',
        ]);

        $sale->loadMissing(['client', 'items', 'rider']);
        $targetPhone = $request->phone ?? $sale->rider?->phone;

        if (! $targetPhone) {
            return response()->json(['message' => 'No se encontró un teléfono de destino.'], 422);
        }

        $message = $this->whatsappService->formatSaleMessage($sale);
        $sent = $this->whatsappService->sendMessage($targetPhone, $message);

        if (! $sent) {
            return response()->json(['message' => 'No se pudo enviar el mensaje por WhatsApp.'], 502);
        }

        return response()->json([
            'message' => 'Mensaje enviado correctamente.',
            'target_phone' => $targetPhone,
            'whatsapp_message' => $message,
        ]);
    }
}
