<?php

namespace Tests\Feature;

use App\Models\SalaryAdvance;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayrollManagementTest extends TestCase
{
    private User $owner;

    private User $employee;

    private int $businessId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['audits', 'salary_advances', 'payroll_payments', 'attendance_logs', 'user_payroll_configs', 'expenses', 'categories', 'businesses', 'users'] as $table) {
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
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->unsignedBigInteger('business_id');
            $table->timestamps();
        });
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('created_by');
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('user_payroll_configs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('business_id');
            $table->decimal('base_salary', 10, 2);
            $table->string('payment_frequency');
            $table->date('start_date')->nullable();
            $table->json('work_schedule')->nullable();
            $table->timestamps();
        });
        Schema::create('attendance_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('business_id');
            $table->date('date');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'date']);
        });
        Schema::create('payroll_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->decimal('base_salary', 10, 2);
            $table->decimal('advances_discounted', 10, 2)->default(0);
            $table->decimal('carryover_amount', 10, 2)->default(0);
            $table->decimal('final_payment', 10, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('salary_advances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('business_id');
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->string('description')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('payroll_payment_id')->nullable();
            $table->unsignedBigInteger('origin_payroll_payment_id')->nullable();
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('audits', function (Blueprint $table): void {
            $table->id();
            $table->string('event');
            $table->morphs('auditable');
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

        $ownerId = DB::table('users')->insertGetId([
            'first_name' => 'Admin', 'last_name' => 'Principal', 'email' => 'admin@test.local',
            'password' => bcrypt('password'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'Negocio', 'user_id' => $ownerId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('users')->where('id', $ownerId)->update(['business_id' => $this->businessId]);
        $employeeId = DB::table('users')->insertGetId([
            'business_id' => $this->businessId, 'first_name' => 'Empleado', 'last_name' => 'Uno',
            'email' => 'empleado@test.local', 'password' => bcrypt('password'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->owner = User::findOrFail($ownerId);
        $this->employee = User::findOrFail($employeeId);
        Sanctum::actingAs($this->owner);
    }

    public function test_start_date_and_bulk_absences_are_used_by_calculation(): void
    {
        $this->putJson('/api/payroll/config/'.$this->employee->id, [
            'base_salary' => 100,
            'payment_frequency' => 'daily',
            'start_date' => '2026-11-01',
        ])->assertOk();
        $this->assertStringStartsWith('2026-11-01', DB::table('user_payroll_configs')->value('start_date'));

        DB::table('attendance_logs')->insert([
            'user_id' => $this->employee->id,
            'business_id' => $this->businessId,
            'date' => '2026-11-02',
            'status' => 'worked',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/payroll/attendance/bulk-absences', [
            'user_id' => $this->employee->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-03',
            'notes' => 'Carga masiva',
        ])->assertCreated()
            ->assertJsonPath('counts.created', 2)
            ->assertJsonPath('counts.skipped', 1);

        $this->getJson('/api/payroll/calculate/'.$this->employee->id.'?end_date=2026-11-03')
            ->assertOk()
            ->assertJsonPath('period.start', '2026-11-01')
            ->assertJsonPath('summary.days_worked', 1)
            ->assertJsonPath('summary.absences', 2)
            ->assertJsonPath('summary.gross_salary', 100);
    }

    public function test_advance_crud_keeps_its_expense_in_sync(): void
    {
        $created = $this->postJson('/api/payroll/advances', [
            'user_id' => $this->employee->id,
            'amount' => 20,
            'date' => '2026-11-01',
            'description' => 'Movilidad',
        ])->assertCreated()->json();

        $advanceId = $created['id'];
        $expenseId = $created['expense_id'];

        $this->patchJson('/api/payroll/advances/'.$advanceId, [
            'amount' => 35,
            'description' => 'Movilidad actualizada',
        ])->assertOk()->assertJsonPath('amount', 35);

        $this->assertDatabaseHas('expenses', [
            'id' => $expenseId,
            'amount' => 35,
            'notes' => 'Movilidad actualizada',
            'deleted_at' => null,
        ]);

        $this->deleteJson('/api/payroll/advances/'.$advanceId)->assertOk();
        $this->assertSoftDeleted('salary_advances', ['id' => $advanceId]);
        $this->assertSoftDeleted('expenses', ['id' => $expenseId]);
    }

    public function test_attendance_can_be_shown_updated_deleted_and_recreated(): void
    {
        $attendanceId = $this->postJson('/api/payroll/attendance', [
            'user_id' => $this->employee->id,
            'date' => '2026-11-10',
            'status' => 'absent',
            'notes' => 'Pendiente de sustento',
        ])->assertCreated()->json('id');

        $this->getJson('/api/payroll/attendance/'.$attendanceId)
            ->assertOk()
            ->assertJsonPath('status', 'absent');

        $this->patchJson('/api/payroll/attendance/'.$attendanceId, [
            'status' => 'sick',
            'notes' => 'Descanso medico',
        ])->assertOk()
            ->assertJsonPath('status', 'sick');

        $this->deleteJson('/api/payroll/attendance/'.$attendanceId)->assertOk();
        $this->assertSoftDeleted('attendance_logs', ['id' => $attendanceId]);

        $this->postJson('/api/payroll/attendance', [
            'user_id' => $this->employee->id,
            'date' => '2026-11-10',
            'status' => 'worked',
        ])->assertOk()->assertJsonPath('id', $attendanceId);

        $this->assertDatabaseHas('attendance_logs', [
            'id' => $attendanceId,
            'status' => 'worked',
            'deleted_at' => null,
        ]);
    }

    public function test_excess_advance_becomes_next_day_carryover_without_new_expense(): void
    {
        DB::table('user_payroll_configs')->insert([
            'user_id' => $this->employee->id,
            'business_id' => $this->businessId,
            'base_salary' => 100,
            'payment_frequency' => 'daily',
            'start_date' => '2026-11-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        SalaryAdvance::create([
            'user_id' => $this->employee->id,
            'business_id' => $this->businessId,
            'amount' => 150,
            'date' => '2026-11-01',
            'description' => 'Adelanto grande',
            'status' => 'pending',
        ]);

        $this->postJson('/api/payroll/pay/'.$this->employee->id, [
            'end_date' => '2026-11-01',
            'payment_date' => '2026-11-01',
        ])->assertCreated()
            ->assertJsonPath('payment.advances_discounted', 100)
            ->assertJsonPath('payment.carryover_amount', 50)
            ->assertJsonPath('payment.final_payment', 0)
            ->assertJsonPath('carryover_advance.amount', 50);

        $carryover = DB::table('salary_advances')->where('status', 'pending')->first();
        $this->assertEquals(50, $carryover->amount);
        $this->assertStringStartsWith('2026-11-02', $carryover->date);
        $this->assertNull($carryover->expense_id);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseHas('payroll_payments', [
            'advances_discounted' => 100,
            'carryover_amount' => 50,
            'final_payment' => 0,
            'expense_id' => null,
        ]);
    }
}
