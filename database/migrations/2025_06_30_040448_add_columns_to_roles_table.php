<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('roles', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('description');
            }
        });
    }

    public function down()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['description', 'status']);
        });
    }
};
