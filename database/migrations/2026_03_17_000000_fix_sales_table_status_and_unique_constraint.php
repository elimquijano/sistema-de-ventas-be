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
        // 1. Eliminamos el índice único global de sale_number si existe
        // Usamos una consulta al esquema para ser compatibles con MySQL/MariaDB
        $indexExists = collect(DB::select("SHOW INDEXES FROM sales"))->pluck('Key_name')->contains('sales_sale_number_unique');

        Schema::table('sales', function (Blueprint $table) use ($indexExists) {
            if ($indexExists) {
                $table->dropUnique('sales_sale_number_unique');
            }

            // 2. Creamos el nuevo índice compuesto por business_id y sale_number
            // Solo si no existe ya
            $compositeExists = collect(DB::select("SHOW INDEXES FROM sales"))->pluck('Key_name')->contains('sales_business_id_sale_number_unique');
            if (!$compositeExists) {
                $table->unique(['business_id', 'sale_number']);
            }

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
            // Revertir enum status (hay que limpiar los datos 'debt' antes)
            DB::statement("UPDATE sales SET status = 'pending' WHERE status = 'debt'");
            DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('completed', 'pending', 'cancelled') NOT NULL DEFAULT 'completed'");

            // Para evitar el error 1553 en MySQL: "Cannot drop index ... needed in a foreign key constraint"
            // MySQL requiere que las columnas de una llave foránea estén indexadas.
            // Si el índice único compuesto es el único que cubre business_id, no deja borrarlo.
            $table->index('business_id', 'sales_business_id_index_temp');
            
            $table->dropUnique(['business_id', 'sale_number']);
            $table->unique('sale_number');
        });
    }
};
