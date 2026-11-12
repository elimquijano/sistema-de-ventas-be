<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashRegisterReopeningTest extends TestCase
{
    private User $owner;

    private User $cashier;

    private int $businessId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['cash_registers', 'businesses', 'users'] as $table) {
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
        Schema::create('cash_registers', function (Blueprint $table): void {
            $table->id();
            $table->decimal('initial_amount', 10, 2);
            $table->decimal('manual_inflow', 10, 2)->default(0);
            $table->decimal('credit_collections', 10, 2)->default(0);
            $table->decimal('cash_sales_amount', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2)->nullable();
            $table->decimal('expected_amount', 10, 2)->nullable();
            $table->decimal('difference', 10, 2)->nullable();
            $table->decimal('profit', 10, 2)->default(0);
            $table->string('currency', 3)->default('PEN');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('status')->default('open');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('opened_by');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();
        });
        $ownerId = DB::table('users')->insertGetId([
            'first_name' => 'Admin', 'last_name' => 'Principal', 'email' => 'admin@cash.test',
            'password' => bcrypt('password'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Negocio', 'user_id' => $ownerId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('users')->where('id', $ownerId)->update(['business_id' => $this->businessId]);
        $cashierId = DB::table('users')->insertGetId([
            'business_id' => $this->businessId, 'first_name' => 'Cajero', 'last_name' => 'Uno',
            'email' => 'cashier@cash.test', 'password' => bcrypt('password'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->owner = User::findOrFail($ownerId);
        $this->cashier = User::findOrFail($cashierId);
        Sanctum::actingAs($this->owner);
    }

    public function test_closed_register_is_reopened_by_clearing_close_fields(): void
    {
        $registerId = $this->createClosedRegister();

        $this->postJson('/api/cash-registers/'.$registerId.'/reopen')->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.expected_amount', 350)
            ->assertJsonPath('data.final_amount', null)
            ->assertJsonPath('data.difference', null)
            ->assertJsonPath('data.closed_at', null)
            ->assertJsonPath('data.closed_by', null);

        $this->assertDatabaseHas('cash_registers', [
            'id' => $registerId,
            'status' => 'open',
            'expected_amount' => 350,
            'cash_sales_amount' => 200,
            'final_amount' => null,
            'difference' => null,
            'closed_at' => null,
            'closed_by' => null,
        ]);
        $this->getJson('/api/cash-registers/current?user_id='.$this->cashier->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $registerId);
    }

    public function test_register_cannot_be_reopened_when_cashier_has_another_open_register(): void
    {
        $closedRegisterId = $this->createClosedRegister();
        $openRegisterId = DB::table('cash_registers')->insertGetId([
            'initial_amount' => 50,
            'expected_amount' => 50,
            'currency' => 'PEN',
            'opened_at' => now(),
            'status' => 'open',
            'business_id' => $this->businessId,
            'opened_by' => $this->cashier->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/cash-registers/'.$closedRegisterId.'/reopen')
            ->assertConflict()
            ->assertJsonPath('conflicting_cash_register_id', $openRegisterId);

        $this->assertDatabaseHas('cash_registers', [
            'id' => $closedRegisterId,
            'status' => 'closed',
            'final_amount' => 340,
        ]);
    }

    private function createClosedRegister(): int
    {
        return DB::table('cash_registers')->insertGetId([
            'initial_amount' => 100,
            'manual_inflow' => 25,
            'credit_collections' => 25,
            'cash_sales_amount' => 200,
            'final_amount' => 340,
            'expected_amount' => 350,
            'difference' => -10,
            'profit' => 80,
            'currency' => 'PEN',
            'opened_at' => now()->subHours(8),
            'closed_at' => now()->subHour(),
            'status' => 'closed',
            'business_id' => $this->businessId,
            'opened_by' => $this->cashier->id,
            'closed_by' => $this->owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
