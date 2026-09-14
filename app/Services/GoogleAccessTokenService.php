<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

        $tokenUri = (string) ($serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token');
        $now = time();
        $assertion = $this->signedAssertion($serviceAccount, $tokenUri, $now);
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Google rechazó la autenticación de Firebase: '
                .$response->status().' '.$response->body()
            );
        }

        $token = $response->json();

        if (empty($token['access_token'])) {
            throw new RuntimeException('Google no devolvió un token OAuth para Firebase.');
        }

        $expiresIn = max(60, (int) ($token['expires_in'] ?? 3600) - 60);
        Cache::put($cacheKey, $token['access_token'], now()->addSeconds($expiresIn));

        return (string) $token['access_token'];
    }

    /**
     * @param  array<string, mixed>  $serviceAccount
     */
    private function signedAssertion(array $serviceAccount, string $tokenUri, int $issuedAt): string
    {
        $header = array_filter([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $serviceAccount['private_key_id'] ?? null,
        ]);
        $claims = [
            'iss' => $serviceAccount['client_email'],
            'scope' => self::MESSAGING_SCOPE,
            'aud' => $tokenUri,
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ];
        $unsignedToken = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR))
            .'.'.$this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));

        $privateKey = openssl_pkey_get_private((string) $serviceAccount['private_key']);
        if ($privateKey === false) {
            throw new RuntimeException('La private_key del JSON de Firebase no es válida.');
        }

        $signed = openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $signed) {
            throw new RuntimeException('No se pudo firmar la solicitud OAuth de Firebase con OpenSSL.');
        }

        return $unsignedToken.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
