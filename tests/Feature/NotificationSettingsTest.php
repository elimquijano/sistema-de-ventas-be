<?php

namespace Tests\Feature;

use App\Exceptions\InvalidFcmTokenException;
use App\Models\User;
use App\Services\FirebaseCloudMessagingService;
use App\Services\NotificationDeliveryService;
use App\Services\WhatsAppService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('status')->default('active');
            $table->boolean('receive_notifications')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        $migration = require database_path(
            'migrations/2026_09_13_000000_add_notification_delivery_to_users_table.php'
        );
        $migration->up();
    }

    public function test_user_can_create_update_and_delete_its_fcm_token(): void
    {
        $user = User::factory()->create(['phone' => '999999999']);
        Sanctum::actingAs($user);

        $this->postJson('/api/notification-settings/token', [
            'token' => 'first-firebase-installation-id',
            'target_type' => 'fid',
        ])
            ->assertCreated()
            ->assertExactJson([
                'receive_notifications' => false,
                'notification_channel' => 'push',
                'has_fcm_token' => true,
                'fcm_target_type' => 'fid',
            ]);

        $this->assertSame('first-firebase-installation-id', $user->refresh()->fcm_token);
        $this->assertSame('fid', $user->fcm_target_type);

        $this->putJson('/api/notification-settings/token', ['token' => 'rotated-firebase-installation-id'])
            ->assertOk()
            ->assertJsonPath('has_fcm_token', true)
            ->assertJsonPath('fcm_target_type', 'fid');

        $this->assertSame('rotated-firebase-installation-id', $user->refresh()->fcm_token);

        $this->deleteJson('/api/notification-settings/token')->assertNoContent();
        $user->refresh();
        $this->assertNull($user->fcm_token);
        $this->assertSame('whatsapp', $user->notification_channel);
        $this->assertFalse($user->receive_notifications);
    }

    public function test_a_device_token_is_moved_to_the_latest_authenticated_user(): void
    {
        $firstUser = User::factory()->create([
            'fcm_token' => 'shared-device-token',
            'notification_channel' => 'push',
            'receive_notifications' => false,
            'phone' => '988888888',
        ]);
        $secondUser = User::factory()->create();
        Sanctum::actingAs($secondUser);

        $this->postJson('/api/notification-settings/token', ['token' => 'shared-device-token'])
            ->assertCreated();

        $this->assertNull($firstUser->refresh()->fcm_token);
        $this->assertFalse($firstUser->receive_notifications);
        $this->assertSame('whatsapp', $firstUser->notification_channel);
        $this->assertSame('shared-device-token', $secondUser->refresh()->fcm_token);
        $this->assertSame('push', $secondUser->notification_channel);
        $this->assertFalse($secondUser->receive_notifications);
    }

    public function test_registering_a_token_automatically_enables_push(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/notification-settings', [
            'notification_channel' => 'push',
            'receive_notifications' => true,
        ])->assertUnprocessable();

        $this->postJson('/api/notification-settings/token', ['token' => 'valid-fcm-token'])
            ->assertCreated()
            ->assertExactJson([
                'receive_notifications' => false,
                'notification_channel' => 'push',
                'has_fcm_token' => true,
                'fcm_target_type' => 'token',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'notification_channel' => 'push',
            'receive_notifications' => false,
        ]);
    }

    public function test_notification_settings_require_authentication(): void
    {
        $this->getJson('/api/notification-settings')->assertUnauthorized();
        $this->postJson('/api/notification-settings/token', ['token' => 'token'])->assertUnauthorized();
    }

    public function test_users_api_cannot_enable_push_without_a_registered_identifier(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/users/{$user->id}", [
            'notification_channel' => 'push',
            'receive_notifications' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('notification_channel');
    }

    public function test_users_api_cannot_create_enabled_push_user_without_a_device(): void
    {
        $creator = User::factory()->create();
        Sanctum::actingAs($creator);

        $this->postJson('/api/users', [
            'first_name' => 'Ana',
            'last_name' => 'Torres',
            'email' => 'ana@example.test',
            'password' => 'secret123',
            'notification_channel' => 'push',
            'receive_notifications' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('notification_channel');
    }

    public function test_whatsapp_cannot_be_enabled_without_a_phone(): void
    {
        $user = User::factory()->create(['phone' => null]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/notification-settings', [
            'notification_channel' => 'whatsapp',
            'receive_notifications' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('notification_channel');
    }

    public function test_setting_a_user_inactive_removes_push_and_restores_whatsapp(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'fcm_token' => 'active-device',
            'fcm_target_type' => 'fid',
            'notification_channel' => 'push',
            'receive_notifications' => true,
        ])->save();
        Sanctum::actingAs($user);

        $this->patchJson("/api/users/{$user->id}/status", [
            'status' => 'inactive',
        ])->assertOk()
            ->assertJsonPath('status', 'inactive')
            ->assertJsonPath('notification_channel', 'whatsapp')
            ->assertJsonPath('receive_notifications', true)
            ->assertJsonPath('has_fcm_token', false);

        $user->refresh();
        $this->assertNull($user->fcm_token);
        $this->assertSame('token', $user->getRawOriginal('fcm_target_type'));
    }

    public function test_inactive_users_cannot_register_push(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);
        Sanctum::actingAs($user);

        $this->postJson('/api/notification-settings/token', [
            'token' => 'inactive-device',
            'target_type' => 'fid',
        ])->assertForbidden();

        $this->assertNull($user->refresh()->fcm_token);
        $this->assertSame('whatsapp', $user->notification_channel);
    }

    public function test_switching_from_push_to_whatsapp_removes_the_identifier(): void
    {
        $user = User::factory()->create(['phone' => '999999999']);
        $user->forceFill([
            'fcm_token' => 'push-device',
            'fcm_target_type' => 'fid',
            'notification_channel' => 'push',
            'receive_notifications' => true,
        ])->save();
        Sanctum::actingAs($user);

        $this->patchJson('/api/notification-settings', [
            'notification_channel' => 'whatsapp',
            'receive_notifications' => true,
        ])->assertOk()
            ->assertJsonPath('notification_channel', 'whatsapp')
            ->assertJsonPath('has_fcm_token', false);

        $this->assertNull($user->refresh()->fcm_token);
    }

    public function test_token_operations_preserve_an_explicit_system_opt_in(): void
    {
        $user = User::factory()->create(['receive_notifications' => true]);
        Sanctum::actingAs($user);

        $this->postJson('/api/notification-settings/token', ['token' => 'first-device'])
            ->assertCreated()->assertJsonPath('receive_notifications', true);

        $this->putJson('/api/notification-settings/token', ['token' => 'second-device'])
            ->assertOk()->assertJsonPath('receive_notifications', true);

        $this->deleteJson('/api/notification-settings/token')->assertNoContent();
        $this->assertTrue($user->refresh()->receive_notifications);
    }

    public function test_system_preference_can_be_set_without_a_phone_or_push_token(): void
    {
        $user = User::factory()->create(['phone' => null]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/notification-settings', ['receive_notifications' => true])
            ->assertOk()
            ->assertJsonPath('receive_notifications', true)
            ->assertJsonPath('has_fcm_token', false);

        $this->assertTrue($user->refresh()->receive_notifications);
    }

    public function test_push_token_survives_time_and_unrelated_user_updates(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/notification-settings/token', ['token' => 'persistent-device'])
            ->assertCreated();

        $this->travel(12)->hours();
        try {
            $user->refresh()->update(['first_name' => 'Nombre actualizado']);

            $this->assertSame('persistent-device', $user->refresh()->fcm_token);
            $this->assertSame('push', $user->notification_channel);
            $this->assertFalse($user->receive_notifications);
        } finally {
            $this->travelBack();
        }
    }

    public function test_changing_system_opt_in_does_not_disable_push_or_delete_its_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/notification-settings/token', ['token' => 'rider-device'])
            ->assertCreated();

        foreach ([true, false] as $enabled) {
            $this->patchJson('/api/notification-settings', ['receive_notifications' => $enabled])
                ->assertOk()
                ->assertJsonPath('receive_notifications', $enabled)
                ->assertJsonPath('notification_channel', 'push')
                ->assertJsonPath('has_fcm_token', true);
        }

        $this->assertSame('rider-device', $user->refresh()->fcm_token);
    }

    public function test_legacy_token_is_not_silently_deleted_by_an_unrelated_profile_save(): void
    {
        $user = User::factory()->create();
        User::query()->whereKey($user->id)->update(['fcm_token' => 'legacy-device']);
        $user->refresh()->update(['first_name' => 'Perfil actualizado']);

        $this->assertSame('legacy-device', $user->refresh()->fcm_token);
    }

    public function test_firebase_unregistered_removes_only_the_invalid_token_and_preserves_opt_in(): void
    {
        $user = User::factory()->create([
            'notification_channel' => 'push',
            'fcm_token' => 'expired-device',
            'receive_notifications' => true,
        ]);
        $firebase = Mockery::mock(FirebaseCloudMessagingService::class);
        $firebase->shouldReceive('send')->once()->andThrow(new InvalidFcmTokenException('UNREGISTERED'));
        $whatsApp = Mockery::mock(WhatsAppService::class);

        $this->assertFalse((new NotificationDeliveryService($firebase, $whatsApp))->send($user, [
            'title' => 'Sistema', 'body' => 'Mensaje',
        ]));

        $this->assertNull($user->refresh()->fcm_token);
        $this->assertSame('whatsapp', $user->notification_channel);
        $this->assertTrue($user->receive_notifications);
    }

    public function test_unregistered_response_for_an_old_token_does_not_delete_a_rotated_token(): void
    {
        $user = User::factory()->create([
            'notification_channel' => 'push',
            'fcm_token' => 'old-device',
        ]);
        $firebase = Mockery::mock(FirebaseCloudMessagingService::class);
        $firebase->shouldReceive('send')->once()->andReturnUsing(function () use ($user): bool {
            User::query()->whereKey($user->id)->update(['fcm_token' => 'new-device']);
            throw new InvalidFcmTokenException('UNREGISTERED');
        });
        $whatsApp = Mockery::mock(WhatsAppService::class);

        $this->assertFalse((new NotificationDeliveryService($firebase, $whatsApp))->send($user, [
            'title' => 'Pedido', 'body' => 'Mensaje',
        ], false));

        $this->assertSame('new-device', $user->refresh()->fcm_token);
        $this->assertSame('push', $user->notification_channel);
        $this->assertFalse($user->receive_notifications);
    }

    public function test_temporary_push_errors_do_not_remove_the_token_or_change_system_preference(): void
    {
        $user = User::factory()->create([
            'notification_channel' => 'push',
            'fcm_token' => 'valid-device',
        ]);
        $firebase = Mockery::mock(FirebaseCloudMessagingService::class);
        $firebase->shouldReceive('send')->once()->andThrow(new \RuntimeException('Firebase 503'));
        $whatsApp = Mockery::mock(WhatsAppService::class);

        $this->assertFalse((new NotificationDeliveryService($firebase, $whatsApp))->send($user, [
            'title' => 'Pedido', 'body' => 'Mensaje',
        ], false));

        $this->assertSame('valid-device', $user->refresh()->fcm_token);
        $this->assertSame('push', $user->notification_channel);
        $this->assertFalse($user->receive_notifications);
    }
}
