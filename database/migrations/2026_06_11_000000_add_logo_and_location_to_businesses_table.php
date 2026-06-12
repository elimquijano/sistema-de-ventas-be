<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('currency');
            $table->decimal('latitude', 10, 8)->nullable()->after('logo_path');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->integer('zoom')->nullable()->after('longitude');
        });
    }

    public function down()
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'latitude', 'longitude', 'zoom']);
        });
    }
};
