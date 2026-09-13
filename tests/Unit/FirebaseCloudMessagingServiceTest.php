<?php

namespace Tests\Unit;

use App\Exceptions\InvalidFcmTokenException;
use App\Services\FirebaseCloudMessagingService;
use App\Services\GoogleAccessTokenService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class FirebaseCloudMessagingServiceTest extends TestCase
{
    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'firebase-test-');
        file_put_contents($this->credentialsPath, json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'client_email' => 'firebase@test-project.iam.gserviceaccount.com',
            'private_key' => 'test-private-key',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], JSON_THROW_ON_ERROR));

        config(['services.firebase.credentials' => $this->credentialsPath]);
        $accessTokens = Mockery::mock(GoogleAccessTokenService::class);
        $accessTokens->shouldReceive('forServiceAccount')
            ->once()
            ->andReturn('oauth-access-token');
        app()->instance(GoogleAccessTokenService::class, $accessTokens);
    }

    protected function tearDown(): void
    {
        if (isset($this->credentialsPath) && is_file($this->credentialsPath)) {
            unlink($this->credentialsPath);
        }

        parent::tearDown();
    }

    public function test_it_authenticates_and_sends_an_http_v1_message(): void
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'name' => 'projects/test-project/messages/message-id',
            ]),
        ]);

        $sent = app(FirebaseCloudMessagingService::class)->send(
            'firebase-installation-id',
            'Nuevo pedido',
            'Tienes un nuevo pedido.',
            ['sale_id' => 15, 'type' => 'order_assigned'],
            'fid'
        );

        $this->assertTrue($sent);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/test-project/messages:send'
            && $request->hasHeader('Authorization', 'Bearer oauth-access-token')
            && $request['message']['fid'] === 'firebase-installation-id'
            && $request['message']['data']['sale_id'] === '15'
        );
    }

    public function test_it_identifies_an_unregistered_device_token(): void
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'error' => [
                    'details' => [[
                        '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                        'errorCode' => 'UNREGISTERED',
                    ]],
                ],
            ], 404),
        ]);

        $this->expectException(InvalidFcmTokenException::class);

        app(FirebaseCloudMessagingService::class)->send(
            'expired-device-token',
            'Título',
            'Mensaje',
            [],
            'token'
        );
    }
}
