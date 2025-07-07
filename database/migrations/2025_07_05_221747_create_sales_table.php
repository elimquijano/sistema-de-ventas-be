<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique();
            $table->string('customer_name')->default('Cliente General');
            $table->decimal('total_amount', 10, 2);
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'credit']);
            $table->enum('payment_status', ['paid', 'pending', 'overdue'])->default('paid');
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('cash_register_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales');
    }
};
