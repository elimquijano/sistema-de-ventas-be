<?php

namespace App\Services;

use App\Exceptions\InvalidFcmTokenException;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationDeliveryService
{
    public function __construct(
        private readonly FirebaseCloudMessagingService $firebase,
        private readonly WhatsAppService $whatsApp,
    ) {}

    /**
     * @param  array{title:string, body:string, data?:array<string, mixed>, whatsapp_body?:string}  $message
     */
    public function send(User $user, array $message): bool
    {
        if ($user->status !== 'active' || ! $user->receive_notifications) {
            return false;
        }

        return match ($user->notification_channel) {
            'push' => $this->sendPush($user, $message),
            'sms' => $this->logMissingSmsProvider($user),
            default => $this->sendWhatsApp($user, $message),
        };
    }

    /** @param array{title:string, body:string, data?:array<string, mixed>, whatsapp_body?:string} $message */
    private function sendPush(User $user, array $message): bool
    {
        if (! $user->fcm_token) {
            Log::warning('No se envió push: el usuario no tiene token FCM.', ['user_id' => $user->id]);

            return false;
        }

        try {
            return $this->firebase->send(
                $user->fcm_token,
                $message['title'],
                $message['body'],
                $message['data'] ?? [],
                $user->fcm_target_type ?? 'token',
            );
        } catch (InvalidFcmTokenException $exception) {
            $values = [
                'fcm_token' => null,
                'fcm_target_type' => 'token',
                'notification_channel' => 'whatsapp',
                'receive_notifications' => $user->status === 'active' && ! empty($user->phone),
            ];

            $user->forceFill($values)->saveQuietly();
            Log::notice('Se eliminó un token FCM no registrado.', ['user_id' => $user->id]);

            return false;
        } catch (Throwable $exception) {
            Log::error('Error al enviar una notificación push.', [
                'user_id' => $user->id,
                'exception' => $exception,
            ]);

            return false;
        }
    }

    /** @param array{title:string, body:string, data?:array<string, mixed>, whatsapp_body?:string} $message */
    private function sendWhatsApp(User $user, array $message): bool
    {
        if (! $user->phone) {
            Log::warning('No se envió WhatsApp: el usuario no tiene teléfono.', ['user_id' => $user->id]);

            return false;
        }

        return $this->whatsApp->sendMessage(
            $user->phone,
            $message['whatsapp_body'] ?? "*{$message['title']}*\n\n{$message['body']}"
        );
    }

    private function logMissingSmsProvider(User $user): bool
    {
        Log::warning('No se envió SMS: aún no hay un proveedor SMS configurado.', [
            'user_id' => $user->id,
        ]);

        return false;
    }
}
