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
        $path = $this->resolvedCredentialsPath();

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

    public function resolvedCredentialsPath(): string
    {
        $configuredPath = trim((string) config('services.firebase.credentials'));

        if ($configuredPath !== '') {
            $path = $this->absolutePath($configuredPath);

            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        $directories = $this->credentialDirectories($configuredPath);
        foreach ($directories as $directory) {
            if (! is_dir($directory) || ! is_readable($directory)) {
                continue;
            }

            $files = collect(glob($directory.DIRECTORY_SEPARATOR.'*.json') ?: [])
                ->filter(fn (string $file): bool => $this->isServiceAccountFile($file))
                ->unique()
                ->values();

            if ($files->count() === 1) {
                return $files->first();
            }

            if ($files->count() > 1) {
                throw new RuntimeException(
                    "Se encontraron varias cuentas de servicio Firebase en {$directory}. Define GOOGLE_APPLICATION_CREDENTIALS con una ruta exacta."
                );
            }
        }

        throw new RuntimeException(
            'PHP no encontró un JSON de cuenta de servicio Firebase legible. Rutas revisadas: '
            .implode(', ', $directories)
        );
    }

    /** @return array<int, string> */
    private function credentialDirectories(string $configuredPath): array
    {
        $configuredDirectory = (string) config(
            'services.firebase.credentials_directory',
            storage_path('app/firebase')
        );
        $directories = [
            $configuredDirectory,
            storage_path('app/firebase'),
            storage_path('app/private/firebase'),
            storage_path('app/private'),
            storage_path('app'),
            base_path('firebase'),
            base_path(),
        ];

        if ($configuredPath !== '') {
            $absolutePath = $this->absolutePath($configuredPath);
            array_unshift(
                $directories,
                is_dir($absolutePath) ? $absolutePath : dirname($absolutePath)
            );
        }

        return collect($directories)
            ->filter()
            ->map(fn (string $directory): string => rtrim($directory, '\\/'))
            ->unique()
            ->values()
            ->all();
    }

    private function isServiceAccountFile(string $path): bool
    {
        if (! is_file($path) || ! is_readable($path)) {
            return false;
        }

        try {
            $contents = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return ($contents['type'] ?? null) === 'service_account'
            && ! empty($contents['project_id'])
            && ! empty($contents['client_email'])
            && ! empty($contents['private_key']);
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
