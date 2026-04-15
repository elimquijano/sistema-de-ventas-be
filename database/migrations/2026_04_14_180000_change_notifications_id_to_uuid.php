<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vaciamos la tabla si tiene basura para evitar errores de conversión
        DB::table('notifications')->truncate();

        Schema::table('notifications', function (Blueprint $table) {
            // Eliminamos el ID autoincremental y añadimos el UUID
            $table->dropColumn('id');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary()->first();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('id');
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->id()->first();
        });
    }
};
