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

class QuickOrderTest extends TestCase
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
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
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
        DB::table('products')->insert([
            ['id' => 2, 'name' => 'Producto dos', 'price' => 8,
                'stock' => 10, 'business_id' => $businessId],
            ['id' => 3, 'name' => 'Producto tres', 'price' => 5,
                'stock' => 10, 'business_id' => $businessId],
        ]);
        DB::table('services')->insert([
            'id' => 1, 'name' => 'Servicio', 'price' => 4, 'business_id' => $businessId,
        ]);

        Sanctum::actingAs($owner);
        $this->riderId = $rider->id;
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendMessage')->zeroOrMoreTimes()->andReturnTrue();
        $this->app->instance(WhatsAppService::class, $whatsApp);
    }

    private int $riderId;

    public function test_quick_order_is_created_when_whatsapp_attempt_fails(): void
    {
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendMessage')->once()->andReturnFalse();
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
        $whatsApp->shouldReceive('sendMessage')->once()
            ->andThrow(new \RuntimeException('WhatsApp no disponible'));
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $this->postJson('/api/sales/quick-order', $this->orderData())
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $this->assertSame(1, Sale::count());
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_quick_order_attempts_real_whatsapp_delivery(): void
    {
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendMessage')->once()->andReturnTrue();
        $this->app->instance(WhatsAppService::class, $whatsApp);

        $this->postJson('/api/sales/quick-order', $this->orderData())
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $this->assertSame(1, Sale::count());
    }

    public function test_editing_multiple_items_adjusts_only_the_net_stock_difference(): void
    {
        $saleId = $this->createOrderWithItems([
            ['id' => 1, 'type' => 'product', 'quantity' => 2],
            ['id' => 2, 'type' => 'product', 'quantity' => 1],
            ['id' => 1, 'type' => 'service', 'quantity' => 1],
        ]);

        $update = [
            'items' => [
                ['id' => 1, 'type' => 'product', 'quantity' => 1],
                ['id' => 2, 'type' => 'product', 'quantity' => 3],
                ['id' => 3, 'type' => 'product', 'quantity' => 2],
                ['id' => 1, 'type' => 'service', 'quantity' => 1],
            ],
            'total_amount' => 100,
        ];

        $this->putJson('/api/sales/quick-order/'.$saleId, $update)
            ->assertOk()
            ->assertJsonCount(4, 'items')
            ->assertJsonPath('total_amount', 100);

        $this->assertSame([1 => 9, 2 => 7, 3 => 8], $this->stocks());
        $this->assertEqualsWithDelta(100, (float) DB::table('sale_items')
            ->where('sale_id', $saleId)->whereNull('deleted_at')->sum('total_price'), 0.001);

        // Reenviar el mismo estado no debe volver a reservar stock.
        $this->putJson('/api/sales/quick-order/'.$saleId, $update)->assertOk();
        $this->assertSame([1 => 9, 2 => 7, 3 => 8], $this->stocks());
    }

    public function test_removing_an_item_returns_its_stock(): void
    {
        $saleId = $this->createOrderWithItems([
            ['id' => 1, 'type' => 'product', 'quantity' => 2],
            ['id' => 2, 'type' => 'product', 'quantity' => 3],
        ]);

        $this->putJson('/api/sales/quick-order/'.$saleId, [
            'items' => [
                ['id' => 1, 'type' => 'product', 'quantity' => 1],
            ],
        ])->assertOk()->assertJsonCount(1, 'items');

        $this->assertSame([1 => 9, 2 => 10, 3 => 10], $this->stocks());
        $this->assertSame(1, DB::table('sale_items')->where('sale_id', $saleId)
            ->whereNotNull('deleted_at')->count());
    }

    public function test_editing_cannot_consume_stock_reserved_by_another_sale(): void
    {
        $firstSaleId = $this->createOrderWithItems([
            ['id' => 1, 'type' => 'product', 'quantity' => 2],
        ]);
        $this->createOrderWithItems([
            ['id' => 1, 'type' => 'product', 'quantity' => 7],
        ]);

        $this->putJson('/api/sales/quick-order/'.$firstSaleId, [
            'items' => [
                ['id' => 1, 'type' => 'product', 'quantity' => 4],
                ['id' => 2, 'type' => 'product', 'quantity' => 2],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertSame([1 => 1, 2 => 10, 3 => 10], $this->stocks());
        $this->assertSame(1, DB::table('sale_items')->where('sale_id', $firstSaleId)
            ->whereNull('deleted_at')->count());
        $this->assertSame(2, (int) DB::table('sale_items')->where('sale_id', $firstSaleId)
            ->whereNull('deleted_at')->value('quantity'));
    }

    public function test_obsolete_top_level_item_fields_are_rejected(): void
    {
        $payload = $this->orderData();
        unset($payload['items']);
        $payload['product_id'] = 1;
        $payload['quantity'] = 2;

        $this->postJson('/api/sales/quick-order', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items', 'product_id', 'quantity']);

        $this->assertSame([1 => 10, 2 => 10, 3 => 10], $this->stocks());
    }

    public function test_a_single_item_uses_the_same_items_contract(): void
    {
        $saleId = $this->createOrderWithItems([
            ['id' => 1, 'type' => 'product', 'quantity' => 2],
        ]);

        $this->putJson('/api/sales/quick-order/'.$saleId, [
            'items' => [['id' => 1, 'type' => 'product', 'quantity' => 4]],
        ])
            ->assertOk()->assertJsonPath('items.0.quantity', 4);

        $this->assertSame([1 => 6, 2 => 10, 3 => 10], $this->stocks());
        $this->assertEqualsWithDelta(25, (float) Sale::findOrFail($saleId)->total_amount, 0.001);
    }

    public function test_repricing_multiple_items_does_not_change_stock(): void
    {
        $saleId = $this->createOrderWithItems([
            ['id' => 1, 'type' => 'product', 'quantity' => 2],
            ['id' => 2, 'type' => 'product', 'quantity' => 1],
        ]);

        $this->putJson('/api/sales/quick-order/'.$saleId, ['total_amount' => 66])
            ->assertOk()->assertJsonCount(2, 'items');

        $this->assertSame([1 => 8, 2 => 9, 3 => 10], $this->stocks());
        $this->assertEqualsWithDelta(66, (float) DB::table('sale_items')
            ->where('sale_id', $saleId)->sum('total_price'), 0.001);
    }

    public function test_duplicate_requested_product_lines_are_counted_together(): void
    {
        $saleId = $this->createOrderWithItems([
            ['id' => 1, 'type' => 'product', 'quantity' => 1],
        ]);

        $this->putJson('/api/sales/quick-order/'.$saleId, [
            'items' => [
                ['id' => 1, 'type' => 'product', 'quantity' => 3],
                ['id' => 1, 'type' => 'product', 'quantity' => 2],
            ],
        ])->assertOk()->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.quantity', 5);

        $this->assertSame([1 => 5, 2 => 10, 3 => 10], $this->stocks());
    }

    public function test_an_edit_cannot_remove_every_item(): void
    {
        $saleId = $this->createOrderWithItems([
            ['id' => 1, 'type' => 'product', 'quantity' => 2],
        ]);

        $this->putJson('/api/sales/quick-order/'.$saleId, [
            'items' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertSame([1 => 8, 2 => 10, 3 => 10], $this->stocks());
        $this->assertSame(2, (int) DB::table('sale_items')->where('sale_id', $saleId)
            ->whereNull('deleted_at')->value('quantity'));
    }

    private function createOrderWithItems(array $items): int
    {
        $payload = $this->orderData();
        $payload['items'] = $items;

        return (int) $this->postJson('/api/sales/quick-order', $payload)
            ->assertCreated()->json('id');
    }

    private function stocks(): array
    {
        return DB::table('products')->orderBy('id')->pluck('stock', 'id')
            ->map(fn ($stock) => (int) $stock)->all();
    }

    private function orderData(): array
    {
        return [
            'phone' => '999111222',
            'customer_name' => 'Cliente',
            'address' => 'Calle 1',
            'rider_id' => $this->riderId,
            'items' => [
                ['id' => 1, 'type' => 'product', 'quantity' => 1],
            ],
        ];
    }
}
