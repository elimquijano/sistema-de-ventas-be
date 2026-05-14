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
        Schema::table('asset_loans', function (Blueprint $table) {
            $table->integer('returned_quantity')->default(0)->after('quantity');
            $table->integer('damaged_quantity')->default(0)->after('returned_quantity');
            $table->integer('lost_quantity')->default(0)->after('damaged_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_loans', function (Blueprint $table) {
            $table->dropColumn(['returned_quantity', 'damaged_quantity', 'lost_quantity']);
        });
    }
};
