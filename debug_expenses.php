<?php

use App\Models\Expense;
use Carbon\Carbon;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Current Date (UTC): " . now()->toDateTimeString() . "\n";
echo "Today (UTC): " . today()->toDateTimeString() . "\n";
echo "Start of Week (UTC): " . now()->startOfWeek()->toDateTimeString() . "\n";
echo "End of Week (UTC): " . now()->endOfWeek()->toDateTimeString() . "\n";

$expenses = Expense::latest()->limit(5)->get();

echo "\nRecent Expenses:\n";
foreach ($expenses as $expense) {
    echo "ID: {$expense->id}, Date: {$expense->expense_date->toDateString()}, Amount: {$expense->amount}, Created At: {$expense->created_at}\n";
}

