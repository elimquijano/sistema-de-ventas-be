<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_payroll_configs', function (Blueprint $table): void {
            $table->date('start_date')->nullable()->after('payment_frequency');
        });

        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('payroll_payments', function (Blueprint $table): void {
            $table->decimal('carryover_amount', 10, 2)->default(0)->after('advances_discounted');
        });

        Schema::table('salary_advances', function (Blueprint $table): void {
            $table->foreignId('origin_payroll_payment_id')
                ->nullable()
                ->after('payroll_payment_id')
                ->constrained('payroll_payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('salary_advances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('origin_payroll_payment_id');
        });

        Schema::table('payroll_payments', function (Blueprint $table): void {
            $table->dropColumn('carryover_amount');
        });

        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('user_payroll_configs', function (Blueprint $table): void {
            $table->dropColumn('start_date');
        });
    }
};
