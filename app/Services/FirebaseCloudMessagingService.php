<?php

namespace App\Services;

use App\Exceptions\InvalidFcmTokenException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class FirebaseCloudMessagingService
{
    public function __construct(private readonly GoogleAccessTokenService $accessTokens) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function send(
        string $deviceIdentifier,
        string $title,
        string $body,
        array $data = [],
        string $targetType = 'token',
    ): bool {
        $credentials = $this->credentials();
        $projectId = config('services.firebase.project_id') ?: $credentials['project_id'];

        if (! is_string($projectId) || $projectId === '') {
            throw new RuntimeException('El project_id no está definido en las credenciales de Firebase.');
        }

        if (! in_array($targetType, ['token', 'fid'], true)) {
            throw new RuntimeException('El tipo de destino FCM debe ser token o fid.');
        }

        $message = [
            $targetType => $deviceIdentifier,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $this->stringifyData($data),
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default',
                    'channel_id' => config('services.firebase.android_channel_id', 'default'),
                ],
            ],
            'apns' => [
                'payload' => [
                    'aps' => ['sound' => 'default'],
                ],
            ],
        ];

        if ($message['data'] === []) {
            unset($message['data']);
        }

        $response = Http::acceptJson()
            ->withToken($this->accessTokens->forServiceAccount($credentials))
            ->post(
                "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                ['message' => $message]
            );

        if ($response->successful()) {
            return true;
        }

        if ($this->isUnregisteredToken($response)) {
            throw new InvalidFcmTokenException('Firebase indicó que el token FCM ya no está registrado.');
        }

        throw new RuntimeException(
            'Firebase rechazó la notificación: '.$response->status().' '.$response->body()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $configuredPath = (string) config('services.firebase.credentials');
        $path = $this->credentialsPath($configuredPath);

        try {
            $credentials = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('El JSON de credenciales de Firebase no es válido.', 0, $exception);
        }

        if (($credentials['type'] ?? null) !== 'service_account') {
            throw new RuntimeException('Las credenciales de Firebase deben ser de tipo service_account.');
        }

        foreach (['client_email', 'private_key', 'project_id'] as $requiredKey) {
            if (empty($credentials[$requiredKey])) {
                throw new RuntimeException("Falta {$requiredKey} en el JSON de Firebase.");
            }
        }

        return $credentials;
    }

    private function credentialsPath(string $configuredPath): string
    {
        if ($configuredPath !== '') {
            $path = $this->absolutePath($configuredPath);

            if (is_file($path) && is_readable($path)) {
                return $path;
            }

            throw new RuntimeException(
                'GOOGLE_APPLICATION_CREDENTIALS no apunta a un JSON de Firebase legible.'
            );
        }

        $files = glob(storage_path('app/firebase/*.json')) ?: [];
        $files = array_values(array_filter($files, fn (string $file): bool => is_readable($file)));

        if (count($files) === 1) {
            return $files[0];
        }

        if (count($files) > 1) {
            throw new RuntimeException(
                'Hay varios JSON de Firebase. Define GOOGLE_APPLICATION_CREDENTIALS con el archivo que se debe usar.'
            );
        }

        throw new RuntimeException(
            'No se encontró el JSON de Firebase en storage/app/firebase.'
        );
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringifyData(array $data): array
    {
        return collect($data)
            ->mapWithKeys(function (mixed $value, string|int $key): array {
                $stringValue = is_scalar($value) || $value === null
                    ? (string) $value
                    : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                return [(string) $key => $stringValue];
            })
            ->all();
    }

    private function isUnregisteredToken(Response $response): bool
    {
        return collect($response->json('error.details', []))
            ->contains(fn (mixed $detail): bool => is_array($detail) && ($detail['errorCode'] ?? null) === 'UNREGISTERED'
            );
    }
}
