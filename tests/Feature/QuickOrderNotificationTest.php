<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class QuickOrderNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('status')->default('active');
            $table->string('phone')->nullable();
            $table->string('notification_channel')->default('whatsapp');
            $table->string('fcm_target_type')->default('token');
            $table->boolean('receive_notifications')->default(false);
            $table->unsignedBigInteger('business_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('address')->nullable();
            $table->string('address_detail')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('stock');
            $table->unsignedBigInteger('business_id');
            $table->timestamps();
        });
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid');
            $table->string('sale_number');
            $table->string('customer_name');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('rider_id');
            $table->string('delivery_address')->nullable();
            $table->string('delivery_phone');
            $table->text('delivery_notes')->nullable();
            $table->boolean('is_delivery');
            $table->dateTime('scheduled_at');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('created_by');
            $table->decimal('total_amount', 10, 2);
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('item_id');
            $table->string('item_type');
            $table->string('item_name');
            $table->decimal('unit_price', 10, 2);
            $table->integer('quantity');
            $table->decimal('total_price', 10, 2);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('audits', function (Blueprint $table): void {
            $table->id();
            $table->morphs('auditable');
            $table->string('event');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->nullableMorphs('parent');
            $table->string('url')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        $owner = User::factory()->create();
        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Prueba', 'user_id' => $owner->id,
        ]);
        $owner->update(['business_id' => $businessId]);
        $rider = User::factory()->create([
            'business_id' => $businessId, 'phone' => '999888777',
        ]);
        DB::table('products')->insert([
            'id' => 1, 'name' => 'Producto', 'price' => 12.50,
            'stock' => 10, 'business_id' => $businessId,
        ]);

        Sanctum::actingAs($owner);
        $this->riderId = $rider->id;
    }

    private int $riderId;

    public function test_quick_order_is_created_when_whatsapp_attempt_fails(): void
    {
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendMessageWithoutWaiting')->once()->andReturnFalse();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $this->postJson('/api/sales/quick-order', $this->orderData())
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $this->assertSame(1, Sale::count());
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_quick_order_is_created_when_whatsapp_attempt_throws(): void
    {
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendMessageWithoutWaiting')->once()
            ->andThrow(new \RuntimeException('WhatsApp no disponible'));
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $this->postJson('/api/sales/quick-order', $this->orderData())
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $this->assertSame(1, Sale::count());
        $this->assertSame(1, DB::table('notifications')->count());
    }

    private function orderData(): array
    {
        return [
            'phone' => '999111222',
            'customer_name' => 'Cliente',
            'address' => 'Calle 1',
            'product_id' => 1,
            'quantity' => 1,
            'rider_id' => $this->riderId,
        ];
    }
}
