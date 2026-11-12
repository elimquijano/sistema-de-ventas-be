<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\BusinessController;
use App\Models\Business;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SaleProfitSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('expenses');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('products');
        Schema::dropIfExists('sales');

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->decimal('cost', 10, 2)->default(0);
        });
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->decimal('total_amount', 10, 2);
            $table->string('status');
            $table->unsignedBigInteger('business_id')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->softDeletes();
        });
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('item_id');
            $table->string('item_type');
            $table->integer('quantity');
            $table->decimal('total_price', 10, 2);
            $table->softDeletes();
        });
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->decimal('amount', 10, 2);
            $table->dateTime('expense_date');
            $table->softDeletes();
        });
    }

    public function test_migration_captures_existing_profit_and_it_stays_fixed(): void
    {
        DB::table('products')->insert(['id' => 1, 'cost' => 5]);
        DB::table('sales')->insert([
            ['id' => 1, 'total_amount' => 40, 'status' => 'completed'],
            ['id' => 2, 'total_amount' => 20, 'status' => 'pending'],
        ]);
        DB::table('sale_items')->insert([
            [
                'sale_id' => 1,
                'item_id' => 1,
                'item_type' => Product::class,
                'quantity' => 2,
                'total_price' => 30,
            ],
            [
                'sale_id' => 1,
                'item_id' => 1,
                'item_type' => Service::class,
                'quantity' => 1,
                'total_price' => 10,
            ],
            [
                'sale_id' => 2,
                'item_id' => 1,
                'item_type' => Product::class,
                'quantity' => 1,
                'total_price' => 20,
            ],
        ]);

        $migration = require database_path('migrations/2026_11_12_000000_add_profit_amount_to_sales_table.php');
        $migration->up();

        $this->assertEqualsWithDelta(30, (float) DB::table('sales')->where('id', 1)->value('profit_amount'), 0.001);
        $this->assertNull(DB::table('sales')->where('id', 2)->value('profit_amount'));

        DB::table('products')->where('id', 1)->update(['cost' => 20]);

        $this->assertEqualsWithDelta(30, (float) DB::table('sales')->where('id', 1)->value('profit_amount'), 0.001);
    }

    public function test_sale_calculates_profit_from_its_final_line_prices(): void
    {
        $productLine = new SaleItem(['quantity' => 2, 'total_price' => 30]);
        $productLine->item_type = Product::class;
        $productLine->setRelation('item', new Product(['cost' => 5]));

        $serviceLine = new SaleItem(['quantity' => 1, 'total_price' => 10]);
        $serviceLine->item_type = Service::class;
        $serviceLine->setRelation('item', new Service);

        $sale = new Sale;
        $sale->setRelation('items', collect([$productLine, $serviceLine]));

        $this->assertSame(30.0, $sale->calculateProfitAmount());
    }

    public function test_business_metrics_use_the_sale_snapshot_for_profit_and_cost(): void
    {
        $migration = require database_path('migrations/2026_11_12_000000_add_profit_amount_to_sales_table.php');
        $migration->up();

        DB::table('sales')->insert([
            'id' => 1,
            'business_id' => 7,
            'total_amount' => 100,
            'profit_amount' => 30,
            'status' => 'completed',
            'scheduled_at' => '2026-11-12 10:00:00',
        ]);
        DB::table('expenses')->insert([
            'business_id' => 7,
            'amount' => 12,
            'expense_date' => '2026-11-12 12:00:00',
        ]);

        $method = new \ReflectionMethod(BusinessController::class, 'getMetricsForRange');
        $metrics = $method->invoke(
            new BusinessController,
            (new Business)->forceFill(['id' => 7]),
            Carbon::parse('2026-11-12 00:00:00'),
            Carbon::parse('2026-11-12 23:59:59'),
        );

        $this->assertSame(100.0, $metrics['sales']);
        $this->assertSame(30.0, $metrics['profit']);
        $this->assertSame(70.0, $metrics['cogs']);
        $this->assertSame(12.0, $metrics['expenses']);
    }
}
