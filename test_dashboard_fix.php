<?php

use App\Models\Business;
use App\Models\Sale;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Mocking the Controller Logic
function getDateRange($period) {
    switch ($period) {
        case 'day': return [now()->startOfDay(), now()->endOfDay()];
        case 'month': return [now()->startOfMonth(), now()->endOfMonth()];
        case 'year': return [now()->startOfYear(), now()->endOfYear()];
        case 'week': default: return [now()->startOfWeek(Carbon::MONDAY), now()->endOfWeek(Carbon::SUNDAY)];
    }
}

function fillMissingChartData($data, $period, $startDate, $endDate) {
    $filledData = collect();
    $currentDate = $startDate->copy();

    while ($currentDate <= $endDate) {
        $label = '';
        switch ($period) {
            case 'day':
                $label = $currentDate->format('H:00');
                $increment = 'addHour';
                break;
            case 'week':
                $label = $currentDate->locale('es')->isoFormat('dddd');
                $increment = 'addDay';
                break;
            case 'month':
                $label = $currentDate->locale('es')->isoFormat('DD MMM');
                $increment = 'addDay';
                break;
            case 'year':
                $label = $currentDate->locale('es')->isoFormat('MMMM');
                $increment = 'addMonth';
                break;
        }

        // Match case-insensitive
        $existing = $data->first(function ($item) use ($label) {
            return strtolower($item->label) === strtolower($label);
        });

        $filledData->push([
            'label' => $label,
            'value' => $existing ? $existing->value : 0,
        ]);

        $currentDate->$increment();
    }
    return $filledData;
}

function getChartData($business, $type, $period, $startDate, $endDate) {
    $table = $type === 'sales' ? 'sales' : 'expenses';
    $dateColumn = $type === 'sales' ? 'created_at' : 'expense_date';
    $amountColumn = $type === 'sales' ? 'total_amount' : 'amount';

    $start = $type === 'expenses' ? $startDate->toDateString() : $startDate->toDateTimeString();
    $end = $type === 'expenses' ? $endDate->toDateString() : $endDate->toDateTimeString();

    $query = DB::table($table)
        ->where('business_id', $business->id)
        ->whereBetween($dateColumn, [$start, $end]);

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
        // ... other cases
    }

    $data = $query->select(DB::raw($selectSQL))
        ->groupBy(DB::raw($groupBySQL))
        ->orderBy(DB::raw($orderBySQL), 'asc')
        ->get();

    return fillMissingChartData($data, $period, $startDate, $endDate);
}

// EXECUTION
DB::statement("SET lc_time_names = 'es_ES'");
$business = Business::first();
$period = 'week';
[$startDate, $endDate] = getDateRange($period);

echo "--- CHART DATA TEST (Week: {$startDate->toDateString()} to {$endDate->toDateString()}) ---\\n";

$salesData = getChartData($business, 'sales', $period, $startDate, $endDate);
$expensesData = getChartData($business, 'expenses', $period, $startDate, $endDate);

echo "\nSALES DATA (Count: " . $salesData->count() . "):\\n";
foreach ($salesData as $item) {
    echo "{$item['label']}: {$item['value']}\\n";
}

echo "\nEXPENSES DATA (Count: " . $expensesData->count() . "):\\n";
foreach ($expensesData as $item) {
    echo "{$item['label']}: {$item['value']}\\n";
}

echo "\n--- RECENT ACTIVITIES LOCALE TEST ---\\n";
// Create a dummy sale 3 weeks ago to test diffForHumans
$oldSale = $business->sales()->create([
    'sale_number' => 'TEST-OLD-'.rand(100,999),
    'total_amount' => 100,
    'created_by' => 1,
    'created_at' => now()->subWeeks(3)
]);

$activities = $business->sales()->latest()->limit(1)->get()->toBase()->map(function ($sale) {
    return [
        'description' => $sale->sale_number,
        'time_es' => $sale->created_at->locale('es')->diffForHumans(),
        'time_en' => $sale->created_at->locale('en')->diffForHumans(),
    ];
});

print_r($activities[0]);

// Clean up
$oldSale->delete();

