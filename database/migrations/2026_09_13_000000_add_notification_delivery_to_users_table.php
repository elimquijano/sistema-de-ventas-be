<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('fcm_token')->nullable()->after('receive_notifications');
            $table->string('fcm_target_type', 10)
                ->default('token')
                ->after('fcm_token');
            $table->string('notification_channel', 20)
                ->default('whatsapp')
                ->after('fcm_target_type')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['notification_channel']);
            $table->dropColumn(['fcm_token', 'fcm_target_type', 'notification_channel']);
        });
    }
};
