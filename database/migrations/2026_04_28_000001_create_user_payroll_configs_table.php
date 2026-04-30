<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_payroll_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->decimal('base_salary', 10, 2);
            $table->enum('payment_frequency', ['daily', 'weekly', 'monthly'])->default('monthly');
            $table->json('work_schedule')->nullable(); // Ej: {"mon": true, "tue": true, ...}
            $table->timestamps();
            
            $table->unique(['user_id', 'business_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_payroll_configs');
    }
};
