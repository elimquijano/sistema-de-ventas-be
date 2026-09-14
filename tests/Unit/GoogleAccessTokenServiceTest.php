<?php

namespace Tests\Unit;

use App\Services\GoogleAccessTokenService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAccessTokenServiceTest extends TestCase
{
    public function test_it_creates_a_signed_assertion_and_gets_an_oauth_token_without_google_auth(): void
    {
        $keyOptions = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $localOpenSslConfig = dirname((string) php_ini_loaded_file()).'/extras/openssl/openssl.cnf';
        if (is_file($localOpenSslConfig)) {
            $keyOptions['config'] = $localOpenSslConfig;
        }

        $key = openssl_pkey_new($keyOptions);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $privateKey, null, $keyOptions));

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'generated-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
        ]);

        $credentials = [
            'client_email' => 'firebase-test-'.uniqid().'@example.test',
            'private_key_id' => 'test-key-id',
            'private_key' => $privateKey,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ];

        $token = app(GoogleAccessTokenService::class)->forServiceAccount($credentials);

        $this->assertSame('generated-access-token', $token);
        Http::assertSent(function ($request): bool {
            $parts = explode('.', (string) $request['assertion']);
            $claims = json_decode($this->base64UrlDecode($parts[1]), true);

            return $request->url() === 'https://oauth2.googleapis.com/token'
                && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && count($parts) === 3
                && $claims['scope'] === 'https://www.googleapis.com/auth/firebase.messaging'
                && $claims['aud'] === 'https://oauth2.googleapis.com/token';
        });
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
