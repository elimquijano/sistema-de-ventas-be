<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class NotificationSettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->settings($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_channel' => ['sometimes', 'required', Rule::in(['whatsapp', 'sms', 'push'])],
            'receive_notifications' => ['sometimes', 'required', 'boolean'],
        ]);

        $channel = $validated['notification_channel'] ?? $request->user()->notification_channel;
        $selectingChannel = array_key_exists('notification_channel', $validated);

        if ($selectingChannel && $channel === 'push' && ! $request->user()->fcm_token) {
            return response()->json([
                'message' => 'Registra primero un token FCM antes de elegir el canal push.',
                'errors' => ['notification_channel' => ['El usuario no tiene un token FCM registrado.']],
            ], 422);
        }

        if ($selectingChannel && in_array($channel, ['whatsapp', 'sms'], true) && ! $request->user()->phone) {
            return response()->json([
                'message' => 'Registra primero un teléfono antes de habilitar este canal.',
                'errors' => ['notification_channel' => ['El usuario no tiene un teléfono registrado.']],
            ], 422);
        }

        $request->user()->update($validated);

        return response()->json($this->settings($request->user()->refresh()));
    }

    public function storeToken(Request $request): JsonResponse
    {
        $validated = $this->validateToken($request);

        if ($request->user()->status !== 'active') {
            return response()->json([
                'message' => 'Solo los usuarios activos pueden habilitar notificaciones push.',
                'errors' => ['status' => ['El usuario no está activo.']],
            ], 422);
        }

        $wasCreated = ! $request->user()->fcm_token;

        $this->saveToken(
            $request->user(),
            $validated['token'],
            $validated['target_type'] ?? 'token'
        );

        return response()->json(
            $this->settings($request->user()->refresh()),
            $wasCreated ? 201 : 200
        );
    }

    public function updateToken(Request $request): JsonResponse
    {
        if ($request->user()->status !== 'active') {
            return response()->json([
                'message' => 'Solo los usuarios activos pueden habilitar notificaciones push.',
                'errors' => ['status' => ['El usuario no está activo.']],
            ], 422);
        }

        if (! $request->user()->fcm_token) {
            return response()->json(['message' => 'El usuario no tiene un token FCM registrado.'], 404);
        }

        $validated = $this->validateToken($request);
        $this->saveToken(
            $request->user(),
            $validated['token'],
            $validated['target_type'] ?? $request->user()->fcm_target_type
        );

        return response()->json($this->settings($request->user()->refresh()));
    }

    public function destroyToken(Request $request): JsonResponse
    {
        $request->user()->forceFill([
            'fcm_token' => null,
            'fcm_target_type' => 'token',
            'notification_channel' => 'whatsapp',
        ])->save();

        Log::notice('Registro push eliminado.', [
            'user_id' => $request->user()->id,
            'reason' => 'token_delete_endpoint',
        ]);

        return response()->json(null, 204);
    }

    /** @return array{token:string, target_type?:string} */
    private function validateToken(Request $request): array
    {
        return $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'target_type' => ['sometimes', 'required', Rule::in(['token', 'fid'])],
        ]);
    }

    private function saveToken(User $user, string $token, string $targetType): void
    {
        DB::transaction(function () use ($user, $token, $targetType): void {
            $previousOwners = User::query()
                ->where('fcm_token', $token)
                ->where($user->getKeyName(), '!=', $user->getKey())
                ->get();

            foreach ($previousOwners as $previousOwner) {
                $values = [
                    'fcm_token' => null,
                    'fcm_target_type' => 'token',
                    'notification_channel' => 'whatsapp',
                ];

                $previousOwner->forceFill($values)->save();
                Log::notice('Registro push eliminado.', [
                    'user_id' => $previousOwner->id,
                    'reason' => 'device_reassigned',
                    'new_user_id' => $user->id,
                ]);
            }

            $user->forceFill([
                'fcm_token' => $token,
                'fcm_target_type' => $targetType,
                'notification_channel' => 'push',
            ])->save();
        });
    }

    /** @return array{receive_notifications:bool, notification_channel:string, has_fcm_token:bool, fcm_target_type:?string} */
    private function settings(User $user): array
    {
        return [
            'receive_notifications' => (bool) $user->receive_notifications,
            'notification_channel' => $user->notification_channel,
            'has_fcm_token' => (bool) $user->fcm_token,
            'fcm_target_type' => $user->fcm_token ? $user->fcm_target_type : null,
        ];
    }
}
