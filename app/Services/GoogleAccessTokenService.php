<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class GoogleAccessTokenService
{
    private const MESSAGING_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * @param  array<string, mixed>  $serviceAccount
     */
    public function forServiceAccount(array $serviceAccount): string
    {
        $cacheKey = 'firebase:fcm:oauth-token:'.sha1((string) $serviceAccount['client_email']);

        if ($cachedToken = Cache::get($cacheKey)) {
            return (string) $cachedToken;
        }

        $credentials = new ServiceAccountCredentials(
            self::MESSAGING_SCOPE,
            $serviceAccount
        );
        $token = $credentials->fetchAuthToken();

        if (empty($token['access_token'])) {
            throw new RuntimeException('Google no devolvió un token OAuth para Firebase.');
        }

        $expiresIn = max(60, (int) ($token['expires_in'] ?? 3600) - 60);
        Cache::put($cacheKey, $token['access_token'], now()->addSeconds($expiresIn));

        return (string) $token['access_token'];
    }
}
