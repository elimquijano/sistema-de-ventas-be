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
        Schema::table('sales', function (Blueprint $table) {
            // 1. Eliminamos el índice único global de sale_number
            // Laravel por defecto lo llama: sales_sale_number_unique
            $table->dropUnique('sales_sale_number_unique');

            // 2. Creamos el nuevo índice compuesto por business_id y sale_number
            // Esto permite que cada negocio tenga su propio V-000001
            $table->unique(['business_id', 'sale_number']);

            // 3. Agregamos el estado 'debt' al enum status
            // Usamos DB::statement para MySQL (XAMPP) para que sea 100% compatible
            DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('completed', 'pending', 'cancelled', 'debt') NOT NULL DEFAULT 'completed'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Revertir unicidad (esto podría fallar si ya hay duplicados de otros negocios)
            $table->dropUnique(['business_id', 'sale_number']);
            $table->unique('sale_number');

            // Revertir enum status (hay que limpiar los datos 'debt' antes)
            DB::statement("UPDATE sales SET status = 'pending' WHERE status = 'debt'");
            DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('completed', 'pending', 'cancelled') NOT NULL DEFAULT 'completed'");
        });
    }
};
