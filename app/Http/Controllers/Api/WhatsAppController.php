<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\User;
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

        $rider = $sale->rider;
        $targetPhone = $request->phone ?? ($rider ? $rider->phone : null);

        if (!$targetPhone) {
            return response()->json(['message' => 'No se encontró un teléfono de destino.'], 422);
        }

        $message = $this->whatsappService->formatSaleMessage($sale);
        $this->whatsappService->sendMessage($targetPhone, $message);

        return response()->json([
            'message' => 'Mensaje enviado correctamente.',
            'target_phone' => $targetPhone,
            'whatsapp_message' => $message
        ]);
    }
}
