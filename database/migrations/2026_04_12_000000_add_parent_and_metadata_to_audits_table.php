<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            // Permite agrupar auditorías bajo un mismo padre (ej: Sale)
            $table->nullableMorphs('parent'); 
            // Para guardar nombres resueltos (ej: "Repartidor: Juan" en vez de "rider_id: 3")
            $table->json('metadata')->nullable()->after('new_values');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropMorphs('parent');
            $table->dropColumn('metadata');
        });
    }
};
