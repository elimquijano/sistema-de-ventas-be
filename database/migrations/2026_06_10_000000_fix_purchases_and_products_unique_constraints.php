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
        // 1. Corregir Compras: de único global a único por negocio
        $purchaseIndex = collect(DB::select("SHOW INDEXES FROM purchases"))->pluck('Key_name');
        
        Schema::table('purchases', function (Blueprint $table) use ($purchaseIndex) {
            if ($purchaseIndex->contains('purchases_purchase_number_unique')) {
                $table->dropUnique('purchases_purchase_number_unique');
            }
            
            if (!$purchaseIndex->contains('purchases_business_id_purchase_number_unique')) {
                $table->unique(['business_id', 'purchase_number']);
            }
        });

        // 2. Corregir Productos: el código de barras debe ser único por negocio
        // Esto permite que dos negocios tengan el mismo producto con el mismo código
        $productIndex = collect(DB::select("SHOW INDEXES FROM products"))->pluck('Key_name');
        
        Schema::table('products', function (Blueprint $table) use ($productIndex) {
            if ($productIndex->contains('products_barcode_unique')) {
                $table->dropUnique('products_barcode_unique');
            }
            
            if (!$productIndex->contains('products_business_id_barcode_unique')) {
                $table->unique(['business_id', 'barcode']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'purchase_number']);
            $table->unique('purchase_number');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'barcode']);
            $table->unique('barcode');
        });
    }
};
