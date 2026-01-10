<?php

use App\Http\Controllers\Api\BusinessController;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$business = Business::first();
$request = new Request();
$request->merge(['period' => 'week']);

// Helper logic from BusinessController
function getDateRange($period) {
    switch ($period) {
        case 'day': return [now()->startOfDay(), now()->endOfDay()];
        case 'month': return [now()->startOfMonth(), now()->endOfMonth()];
        case 'year': return [now()->startOfYear(), now()->endOfYear()];
        case 'week': default: return [now()->startOfWeek(Carbon::MONDAY), now()->endOfWeek(Carbon::SUNDAY)];
    }
}

function getChartData($business, $type, $period, $startDate, $endDate) {
    $table = $type === 'sales' ? 'sales' : 'expenses';
    $dateColumn = $type === 'sales' ? 'created_at' : 'expense_date';
    $amountColumn = $type === 'sales' ? 'total_amount' : 'amount';

    echo "Querying $table between $startDate and $endDate\n";

    $query = DB::table($table)
        ->where('business_id', $business->id)
        ->whereBetween($dateColumn, [$startDate, $endDate]);

    $selectSQL = "";
    $groupBySQL = "";
    $orderBySQL = "MIN({$dateColumn})";

    switch ($period) {
        case 'day':
            $selectSQL = "DATE_FORMAT({$dateColumn}, '%H:00') as label, SUM({$amountColumn}) as value";
            $groupBySQL = "label";
            break;
        case 'week':
            $selectSQL = "DATE_FORMAT({$dateColumn}, '%W') as label, SUM({$amountColumn}) as value";
            $groupBySQL = "label";
            break;
        case 'month':
            $selectSQL = "DATE_FORMAT({$dateColumn}, '%d %b') as label, SUM({$amountColumn}) as value";
            $groupBySQL = "label";
            break;
        case 'year':
            $selectSQL = "DATE_FORMAT({$dateColumn}, '%M') as label, SUM({$amountColumn}) as value";
            $groupBySQL = "label";
            $orderBySQL = "MIN(MONTH({$dateColumn}))";
            break;
    }

    return $query->select(DB::raw($selectSQL))
        ->groupBy(DB::raw($groupBySQL))
        ->orderBy(DB::raw($orderBySQL), 'asc')
        ->get();
}

// Logic from dashboard method
DB::statement("SET lc_time_names = 'es_ES'");

$period = 'week';
[$startDate, $endDate] = getDateRange($period);

$dailyExpenses = $business->expenses()->whereDate('expense_date', today())->sum('amount');
echo "Daily Expenses (Eloquent): $dailyExpenses\n";

$monthlyExpenses = $business->expenses()->whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year)->sum('amount');
echo "Monthly Expenses (Eloquent): $monthlyExpenses\n";

$expensesData = getChartData($business, 'expenses', $period, $startDate, $endDate);
echo "Chart Data (Query Builder):\n";
print_r($expensesData);
