<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Covers the tenant and soft-delete filters plus the listing order,
            // preventing a filesort of every client in the business.
            $table->index(
                ['business_id', 'deleted_at', 'name', 'id'],
                'clients_business_deleted_name_id_index'
            );

            // Speeds up the exact phone filter without scanning every client.
            $table->index(
                ['business_id', 'phone', 'deleted_at'],
                'clients_business_phone_deleted_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_business_deleted_name_id_index');
            $table->dropIndex('clients_business_phone_deleted_index');
        });
    }
};
