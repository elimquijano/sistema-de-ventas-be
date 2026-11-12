<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('profit_amount', 12, 2)->nullable()->after('total_amount');
        });

        DB::table('sales')
            ->whereIn('status', ['completed', 'debt'])
            ->orderBy('id')
            ->chunkById(200, function ($sales): void {
                $saleIds = $sales->pluck('id');
                $profits = DB::table('sale_items')
                    ->leftJoin('products', function ($join): void {
                        $join->on('sale_items.item_id', '=', 'products.id')
                            ->where('sale_items.item_type', '=', 'App\Models\Product');
                    })
                    ->whereIn('sale_items.sale_id', $saleIds)
                    ->whereNull('sale_items.deleted_at')
                    ->select('sale_items.sale_id')
                    ->selectRaw('SUM(CASE
                        WHEN sale_items.item_type = ?
                            THEN sale_items.total_price - (COALESCE(products.cost, 0) * sale_items.quantity)
                        ELSE sale_items.total_price
                    END) AS profit_amount', ['App\Models\Product'])
                    ->groupBy('sale_items.sale_id')
                    ->pluck('profit_amount', 'sale_items.sale_id');

                foreach ($sales as $sale) {
                    DB::table('sales')->where('id', $sale->id)->update([
                        'profit_amount' => round((float) ($profits[$sale->id] ?? 0), 2),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn('profit_amount');
        });
    }
};
