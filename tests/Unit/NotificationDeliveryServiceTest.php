<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\FirebaseCloudMessagingService;
use App\Services\NotificationDeliveryService;
use App\Services\WhatsAppService;
use Mockery;
use Tests\TestCase;

class NotificationDeliveryServiceTest extends TestCase
{
    public function test_it_uses_push_when_that_is_the_users_preferred_channel(): void
    {
        $firebase = Mockery::mock(FirebaseCloudMessagingService::class);
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $user = new User([
            'status' => 'active',
            'receive_notifications' => true,
            'notification_channel' => 'push',
        ]);
        $user->forceFill(['fcm_token' => 'device-token']);

        $firebase->shouldReceive('send')
            ->once()
            ->with('device-token', 'Título', 'Mensaje', ['type' => 'system'], 'token')
            ->andReturnTrue();
        $whatsApp->shouldNotReceive('sendMessage');

        $sent = (new NotificationDeliveryService($firebase, $whatsApp))->send($user, [
            'title' => 'Título',
            'body' => 'Mensaje',
            'data' => ['type' => 'system'],
        ]);

        $this->assertTrue($sent);
    }

    public function test_it_keeps_whatsapp_as_the_default_delivery_channel(): void
    {
        $firebase = Mockery::mock(FirebaseCloudMessagingService::class);
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $user = new User([
            'status' => 'active',
            'receive_notifications' => true,
            'phone' => '999999999',
        ]);

        $firebase->shouldNotReceive('send');
        $whatsApp->shouldReceive('sendMessage')
            ->once()
            ->with('999999999', "*Título*\n\nMensaje")
            ->andReturnTrue();

        $sent = (new NotificationDeliveryService($firebase, $whatsApp))->send($user, [
            'title' => 'Título',
            'body' => 'Mensaje',
        ]);

        $this->assertTrue($sent);
    }

    public function test_it_does_not_send_to_disabled_users(): void
    {
        $firebase = Mockery::mock(FirebaseCloudMessagingService::class);
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $user = new User([
            'status' => 'active',
            'receive_notifications' => false,
            'notification_channel' => 'push',
        ]);
        $user->forceFill(['fcm_token' => 'device-token']);

        $firebase->shouldNotReceive('send');
        $whatsApp->shouldNotReceive('sendMessage');

        $sent = (new NotificationDeliveryService($firebase, $whatsApp))->send($user, [
            'title' => 'Título',
            'body' => 'Mensaje',
        ]);

        $this->assertFalse($sent);
    }
}
