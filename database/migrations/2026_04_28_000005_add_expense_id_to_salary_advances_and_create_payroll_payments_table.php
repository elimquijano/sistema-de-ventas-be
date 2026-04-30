<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Crear la tabla de pagos de planilla
        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->onDelete('set null');
            $table->decimal('base_salary', 10, 2);
            $table->decimal('advances_discounted', 10, 2)->default(0);
            $table->decimal('final_payment', 10, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Añadir la columna de relación a la tabla de adelantos (que ya existe)
        Schema::table('salary_advances', function (Blueprint $table) {
            $table->foreignId('payroll_payment_id')->nullable()->after('status')->constrained()->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('salary_advances', function (Blueprint $table) {
            $table->dropForeign(['payroll_payment_id']);
            $table->dropColumn('payroll_payment_id');
        });
        Schema::dropIfExists('payroll_payments');
    }
};
