<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssetReconciliationTest extends TestCase
{
    private User $owner;

    private int $businessId;

    private int $assetId;

    private int $productOneId;

    private int $productTwoId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'asset_inventory_snapshot_items',
            'asset_inventory_snapshots',
            'asset_inventory_movements',
            'asset_reconciliation_product_mappings',
            'sale_items',
            'sales',
            'products',
            'assets',
            'businesses',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('password');
            $table->string('status')->default('active');
            $table->boolean('receive_notifications')->default(false);
            $table->string('notification_channel')->default('whatsapp');
            $table->string('fcm_token')->nullable();
            $table->string('fcm_target_type')->default('token');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->string('type');
            $table->integer('total_quantity');
            $table->integer('available_quantity');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->integer('min_stock')->default(5);
            $table->string('image_path')->nullable();
            $table->timestamps();
        });
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('status');
            $table->dateTime('scheduled_at');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('item_id');
            $table->string('item_type');
            $table->integer('quantity');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('asset_reconciliation_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('units_per_sale')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'product_id']);
        });
        Schema::create('asset_inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('asset_loan_id')->nullable();
            $table->string('type');
            $table->string('condition')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('counterparty')->nullable();
            $table->dateTime('occurred_at');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('asset_inventory_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('base_snapshot_id')->nullable();
            $table->dateTime('counted_at');
            $table->string('status');
            $table->boolean('confirmed_unbalanced')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('asset_inventory_snapshot_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('snapshot_id');
            $table->unsignedBigInteger('asset_id');
            $table->unsignedInteger('full_quantity');
            $table->unsignedInteger('empty_quantity');
            $table->integer('expected_full_quantity')->nullable();
            $table->integer('expected_empty_quantity')->nullable();
            $table->integer('expected_total_quantity');
            $table->integer('full_difference')->nullable();
            $table->integer('empty_difference')->nullable();
            $table->integer('total_difference');
            $table->unsignedInteger('sales_quantity')->default(0);
            $table->unsignedInteger('loaned_out_quantity')->default(0);
            $table->unsignedInteger('returned_in_quantity')->default(0);
            $table->unsignedInteger('refilled_quantity')->default(0);
            $table->unsignedInteger('external_in_full')->default(0);
            $table->unsignedInteger('external_in_empty')->default(0);
            $table->unsignedInteger('external_out_full')->default(0);
            $table->unsignedInteger('external_out_empty')->default(0);
            $table->string('status');
            $table->json('calculation')->nullable();
            $table->timestamps();
        });

        $ownerId = DB::table('users')->insertGetId([
            'first_name' => 'Admin', 'last_name' => 'Principal', 'email' => 'admin@assets.test',
            'password' => bcrypt('password'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Negocio', 'user_id' => $ownerId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('users')->where('id', $ownerId)->update(['business_id' => $this->businessId]);
        $this->assetId = DB::table('assets')->insertGetId([
            'business_id' => $this->businessId,
            'name' => 'Balon pequeno',
            'type' => 'envase',
            'total_quantity' => 55,
            'available_quantity' => 49,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->productOneId = $this->createProduct('Gas pequeno normal');
        $this->productTwoId = $this->createProduct('Gas pequeno premium');
        $this->owner = User::findOrFail($ownerId);
        Sanctum::actingAs($this->owner);
    }

    public function test_multiple_products_are_configured_without_modifying_products(): void
    {
        $this->putJson('/api/assets/'.$this->assetId.'/reconciliation-products', [
            'products' => [
                ['product_id' => $this->productOneId, 'units_per_sale' => 1],
                ['product_id' => $this->productTwoId, 'units_per_sale' => 2],
            ],
        ])->assertOk()
            ->assertJsonPath('products.0.selected', true)
            ->assertJsonPath('products.1.selected', true);

        $this->assertDatabaseCount('asset_reconciliation_product_mappings', 2);
        $this->assertFalse(Schema::hasColumn('products', 'asset_id'));
    }

    public function test_prepare_and_save_balanced_snapshot_uses_sales_loans_and_returns(): void
    {
        $this->seedReconciliationPeriod();

        $this->getJson('/api/businesses/'.$this->businessId.'/asset-reconciliation/prepare?counted_at=2026-11-13%2023:00:00')
            ->assertOk()
            ->assertJsonPath('items.0.movements.sales', 6)
            ->assertJsonPath('items.0.movements.loaned_out', 2)
            ->assertJsonPath('items.0.movements.returned_in', 1)
            ->assertJsonPath('items.0.expected.full_quantity', 14)
            ->assertJsonPath('items.0.expected.empty_quantity', 35)
            ->assertJsonPath('items.0.expected.total_quantity', 49);

        $this->postJson('/api/businesses/'.$this->businessId.'/asset-reconciliation/snapshots', [
            'counted_at' => '2026-11-13 23:00:00',
            'items' => [[
                'asset_id' => $this->assetId,
                'full_quantity' => 14,
                'empty_quantity' => 35,
            ]],
        ])->assertCreated()
            ->assertJsonPath('report.status', 'balanced')
            ->assertJsonPath('snapshot.status', 'balanced');

        $this->assertDatabaseHas('asset_inventory_snapshot_items', [
            'asset_id' => $this->assetId,
            'sales_quantity' => 6,
            'loaned_out_quantity' => 2,
            'returned_in_quantity' => 1,
            'total_difference' => 0,
            'status' => 'balanced',
        ]);
    }

    public function test_unbalanced_snapshot_requires_confirmation_and_note(): void
    {
        $this->seedReconciliationPeriod();
        $payload = [
            'counted_at' => '2026-11-13 23:00:00',
            'items' => [[
                'asset_id' => $this->assetId,
                'full_quantity' => 13,
                'empty_quantity' => 35,
            ]],
        ];

        $this->postJson('/api/businesses/'.$this->businessId.'/asset-reconciliation/snapshots', $payload)
            ->assertConflict()
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('report.items.0.differences.full', -1);

        $this->assertDatabaseCount('asset_inventory_snapshots', 1);

        $this->postJson('/api/businesses/'.$this->businessId.'/asset-reconciliation/snapshots', [
            ...$payload,
            'confirm_unbalanced' => true,
            'notes' => 'Se verifico fisicamente y falta un balon.',
        ])->assertCreated()
            ->assertJsonPath('snapshot.status', 'unbalanced')
            ->assertJsonPath('snapshot.confirmed_unbalanced', true);
    }

    public function test_external_borrowing_can_be_registered_with_the_snapshot(): void
    {
        $this->seedReconciliationPeriod();

        $this->postJson('/api/businesses/'.$this->businessId.'/asset-reconciliation/snapshots', [
            'counted_at' => '2026-11-13 23:00:00',
            'items' => [[
                'asset_id' => $this->assetId,
                'full_quantity' => 16,
                'empty_quantity' => 35,
            ]],
            'adjustments' => [[
                'asset_id' => $this->assetId,
                'type' => 'external_in',
                'condition' => 'full',
                'quantity' => 2,
                'counterparty' => 'Distribuidora Norte',
            ]],
        ])->assertCreated()
            ->assertJsonPath('report.status', 'balanced')
            ->assertJsonPath('report.items.0.movements.external_in_full', 2)
            ->assertJsonPath('report.items.0.expected.full_quantity', 16);

        $this->assertDatabaseHas('asset_inventory_movements', [
            'asset_id' => $this->assetId,
            'type' => 'external_in',
            'condition' => 'full',
            'quantity' => 2,
            'counterparty' => 'Distribuidora Norte',
        ]);
    }

    public function test_asset_flow_returns_simple_modal_details(): void
    {
        $this->seedReconciliationPeriod();

        $this->getJson('/api/businesses/'.$this->businessId.'/asset-flow/'.$this->assetId.'?from=2026-11-12&to=2026-11-13')
            ->assertOk()
            ->assertJsonPath('summary.outgoing', 2)
            ->assertJsonPath('summary.incoming', 1)
            ->assertJsonPath('summary.net_out', 1)
            ->assertJsonPath('outgoing.0.person', 'Cliente Uno')
            ->assertJsonPath('outgoing.0.quantity', 2);
    }

    private function seedReconciliationPeriod(): void
    {
        DB::table('asset_reconciliation_product_mappings')->insert([
            ['business_id' => $this->businessId, 'asset_id' => $this->assetId, 'product_id' => $this->productOneId, 'units_per_sale' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => $this->businessId, 'asset_id' => $this->assetId, 'product_id' => $this->productTwoId, 'units_per_sale' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $snapshotId = DB::table('asset_inventory_snapshots')->insertGetId([
            'business_id' => $this->businessId,
            'counted_at' => '2026-11-11 23:00:00',
            'status' => 'balanced',
            'created_by' => $this->owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('asset_inventory_snapshot_items')->insert([
            'snapshot_id' => $snapshotId,
            'asset_id' => $this->assetId,
            'full_quantity' => 20,
            'empty_quantity' => 30,
            'expected_total_quantity' => 50,
            'total_difference' => 0,
            'status' => 'balanced',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $saleId = DB::table('sales')->insertGetId([
            'business_id' => $this->businessId,
            'status' => 'completed',
            'scheduled_at' => '2026-11-12 12:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sale_items')->insert([
            ['sale_id' => $saleId, 'item_id' => $this->productOneId, 'item_type' => Product::class, 'quantity' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['sale_id' => $saleId, 'item_id' => $this->productTwoId, 'item_type' => Product::class, 'quantity' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('asset_inventory_movements')->insert([
            [
                'business_id' => $this->businessId,
                'asset_id' => $this->assetId,
                'type' => 'loan_out',
                'condition' => null,
                'quantity' => 2,
                'counterparty' => 'Cliente Uno',
                'occurred_at' => '2026-11-12 13:00:00',
                'created_by' => $this->owner->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'business_id' => $this->businessId,
                'asset_id' => $this->assetId,
                'type' => 'loan_return',
                'condition' => 'empty',
                'quantity' => 1,
                'counterparty' => 'Cliente Dos',
                'occurred_at' => '2026-11-13 09:00:00',
                'created_by' => $this->owner->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function createProduct(string $name): int
    {
        return DB::table('products')->insertGetId([
            'business_id' => $this->businessId,
            'name' => $name,
            'price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
