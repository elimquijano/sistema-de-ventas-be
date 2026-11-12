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
     * @param  array{title:string, body:string, data?:array<string, mixed>, whatsapp_body?:string, fire_and_forget?:bool}  $message
     */
    public function send(User $user, array $message, bool $requiresSystemOptIn = true): bool
    {
        if ($user->status !== 'active'
            || ($requiresSystemOptIn && ! $user->receive_notifications)) {
            return false;
        }

        return match ($user->notification_channel) {
            'push' => $this->sendPush($user, $message),
            'sms' => $this->logMissingSmsProvider($user),
            default => $this->sendWhatsApp($user, $message),
        };
    }

    /** @param array{title:string, body:string, data?:array<string, mixed>, whatsapp_body?:string, fire_and_forget?:bool} $message */
    private function sendPush(User $user, array $message): bool
    {
        if (! $user->fcm_token) {
            Log::warning('No se envió push: el usuario no tiene token FCM.', ['user_id' => $user->id]);

            return false;
        }

        $deviceIdentifier = $user->fcm_token;
        $targetType = $user->fcm_target_type ?? 'token';

        try {
            return $this->firebase->send(
                $deviceIdentifier,
                $message['title'],
                $message['body'],
                $message['data'] ?? [],
                $targetType,
            );
        } catch (InvalidFcmTokenException $exception) {
            $values = [
                'fcm_token' => null,
                'fcm_target_type' => 'token',
                'notification_channel' => 'whatsapp',
            ];

            // No borrar un token nuevo registrado mientras Firebase respondía por el anterior.
            $cleared = User::query()
                ->whereKey($user->id)
                ->where('fcm_token', $deviceIdentifier)
                ->where('fcm_target_type', $targetType)
                ->update($values);

            if ($cleared) {
                $user->refresh();
            }

            Log::notice('Firebase informó un registro push no registrado.', [
                'user_id' => $user->id,
                'reason' => 'firebase_unregistered',
                'token_removed' => (bool) $cleared,
            ]);

            return false;
        } catch (Throwable $exception) {
            Log::error('Error al enviar una notificación push.', [
                'user_id' => $user->id,
                'exception' => $exception,
            ]);

            return false;
        }
    }

    /** @param array{title:string, body:string, data?:array<string, mixed>, whatsapp_body?:string, fire_and_forget?:bool} $message */
    private function sendWhatsApp(User $user, array $message): bool
    {
        if (! $user->phone) {
            Log::warning('No se envió WhatsApp: el usuario no tiene teléfono.', ['user_id' => $user->id]);

            return false;
        }

        $body = $message['whatsapp_body'] ?? "*{$message['title']}*\n\n{$message['body']}";

        return ($message['fire_and_forget'] ?? false)
            ? $this->whatsApp->sendMessageWithoutWaiting($user->phone, $body)
            : $this->whatsApp->sendMessage($user->phone, $body);
    }

    private function logMissingSmsProvider(User $user): bool
    {
        Log::warning('No se envió SMS: aún no hay un proveedor SMS configurado.', [
            'user_id' => $user->id,
        ]);

        return false;
    }
}
