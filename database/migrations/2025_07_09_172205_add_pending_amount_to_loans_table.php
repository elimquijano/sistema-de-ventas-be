<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('pending_amount', 10, 2)->default(0)->after('paid_amount');
        });

        // Optional: Calculate initial pending_amount for existing loans
        DB::table('loans')->update([
            'pending_amount' => DB::raw('amount - paid_amount')
        ]);
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('pending_amount');
        });
    }
};
