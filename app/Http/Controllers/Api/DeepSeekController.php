<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DeepSeekController extends Controller
{
    public function chatCompletions(Request $request)
    {
        $apiKey = config('services.deepseek.api_key');

        if (! $apiKey) {
            return response()->json([
                'error' => [
                    'message' => 'El asistente de voz no está configurado.',
                ],
            ], 503);
        }

        $payload = $request->all();
        $payload['model'] = 'deepseek-v4-flash';
        $payload['stream'] = false;

        $response = Http::acceptJson()
            ->withToken($apiKey)
            ->timeout(60)
            ->post('https://api.deepseek.com/chat/completions', $payload);

        return response($response->body(), $response->status())
            ->header('Content-Type', 'application/json');
    }
}
