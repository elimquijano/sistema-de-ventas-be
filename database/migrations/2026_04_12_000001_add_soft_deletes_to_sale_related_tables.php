<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
