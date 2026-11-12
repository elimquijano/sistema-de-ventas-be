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

    public function test_system_notifications_do_not_send_without_opt_in(): void
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

    public function test_orders_use_push_even_when_system_notifications_are_disabled(): void
    {
        $firebase = Mockery::mock(FirebaseCloudMessagingService::class);
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $user = new User([
            'status' => 'active',
            'receive_notifications' => false,
            'notification_channel' => 'push',
        ]);
        $user->forceFill(['fcm_token' => 'rider-device']);

        $firebase->shouldReceive('send')->once()
            ->with('rider-device', 'Nuevo pedido', 'Pedido asignado', ['type' => 'order_assigned'], 'token')
            ->andReturnTrue();
        $whatsApp->shouldNotReceive('sendMessage');

        $this->assertTrue((new NotificationDeliveryService($firebase, $whatsApp))->send($user, [
            'title' => 'Nuevo pedido',
            'body' => 'Pedido asignado',
            'data' => ['type' => 'order_assigned'],
        ], false));
    }

    public function test_orders_use_whatsapp_even_when_system_notifications_are_disabled(): void
    {
        $firebase = Mockery::mock(FirebaseCloudMessagingService::class);
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $user = new User([
            'status' => 'active',
            'receive_notifications' => false,
            'notification_channel' => 'whatsapp',
            'phone' => '999999999',
        ]);

        $firebase->shouldNotReceive('send');
        $whatsApp->shouldReceive('sendMessageWithoutWaiting')->once()
            ->with('999999999', 'Mensaje bonito del pedido')->andReturnTrue();

        $this->assertTrue((new NotificationDeliveryService($firebase, $whatsApp))->send($user, [
            'title' => 'Nuevo pedido',
            'body' => 'Pedido asignado',
            'whatsapp_body' => 'Mensaje bonito del pedido',
            'fire_and_forget' => true,
        ], false));
    }

    public function test_inactive_riders_do_not_receive_orders(): void
    {
        $firebase = Mockery::mock(FirebaseCloudMessagingService::class);
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $user = new User(['status' => 'inactive', 'receive_notifications' => true]);

        $firebase->shouldNotReceive('send');
        $whatsApp->shouldNotReceive('sendMessage');

        $this->assertFalse((new NotificationDeliveryService($firebase, $whatsApp))->send($user, [
            'title' => 'Nuevo pedido', 'body' => 'Pedido asignado',
        ], false));
    }
}
