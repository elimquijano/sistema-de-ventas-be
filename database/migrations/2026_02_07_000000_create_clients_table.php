<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            
            // Relación con Business (equivalente a Company)
            $table->foreignId('business_id')
                  ->nullable()
                  ->constrained('businesses')
                  ->onDelete('set null');

            // Relación con User que creó el cliente
            $table->foreignId('created_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            // Coordenadas
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            
            $table->text('address')->nullable();
            $table->string('image')->nullable();
            $table->json('route')->nullable(); // Para almacenar la ruta trazada
            
            $table->string('estimated_time')->nullable();
            $table->string('approximate_distance')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
