<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // ej: herramienta, vehiculo, envase, mueble
            $table->integer('total_quantity')->default(1);
            $table->integer('available_quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance', 'lost'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('assets');
    }
};
