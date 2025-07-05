<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('route')->nullable(); // Nueva columna para la ruta
            $table->string('component')->nullable(); // Nueva columna para el componente
            $table->string('permission')->nullable(); // Permiso específico requerido
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->enum('type', ['module', 'group', 'page', 'button'])->default('module');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('show_in_menu')->default(true); // Si se muestra en el sidebar
            $table->boolean('auto_create_permissions')->default(true); // Si crea permisos automáticamente
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('modules')->onDelete('cascade');
            $table->index(['parent_id', 'sort_order']);
            $table->index(['status', 'show_in_menu']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('modules');
    }
};
