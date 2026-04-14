<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->string('event'); // created, updated, deleted, restored
            $table->morphs('auditable'); // auditable_id y auditable_type
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Quién lo hizo
            $table->json('old_values')->nullable(); // Solo los cambios anteriores
            $table->json('new_values')->nullable(); // Solo los cambios nuevos
            $table->string('url')->nullable(); // URL desde donde se hizo (útil para API)
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['auditable_id', 'auditable_type', 'event']); // Indexar para la línea de tiempo
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
