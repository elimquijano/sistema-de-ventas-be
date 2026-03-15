<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('customer_name')->constrained('clients')->onDelete('set null');
            $table->foreignId('rider_id')->nullable()->after('client_id')->constrained('users')->onDelete('set null');
            $table->string('delivery_address')->nullable()->after('rider_id');
            $table->string('delivery_phone')->nullable()->after('delivery_address');
            $table->text('delivery_notes')->nullable()->after('delivery_phone');
            $table->boolean('is_delivery')->default(false)->after('delivery_notes');
            $table->dateTime('scheduled_at')->nullable()->after('is_delivery');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['rider_id']);
            $table->dropColumn(['client_id', 'rider_id', 'delivery_address', 'delivery_phone', 'delivery_notes', 'is_delivery', 'scheduled_at']);
        });
    }
};
