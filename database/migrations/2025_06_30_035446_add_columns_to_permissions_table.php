<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('permissions', 'display_name')) {
                $table->string('display_name')->after('name');
            }
            if (!Schema::hasColumn('permissions', 'module')) {
                $table->string('module')->nullable()->after('display_name');
            }
            if (!Schema::hasColumn('permissions', 'type')) {
                $table->enum('type', ['view', 'create', 'edit', 'delete', 'manage'])->default('view')->after('module');
            }
            if (!Schema::hasColumn('permissions', 'description')) {
                $table->text('description')->nullable()->after('type');
            }
        });
    }

    public function down()
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'module', 'type', 'description']);
        });
    }
};
