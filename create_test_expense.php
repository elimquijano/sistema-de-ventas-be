<?php

use App\Models\Expense;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = User::first(); // Assuming a user exists
if (!$user) {
    echo "No user found.\n";
    exit;
}
Auth::login($user);

$business = Business::first();
if (!$business) {
    echo "No business found.\n";
    exit;
}

echo "Creating expense for business: {$business->name}\n";

$expense = Expense::create([
    'description' => 'Test Expense Today',
    'amount' => 123.45,
    'expense_date' => now()->toDateString(), // Today 2026-01-10
    'business_id' => $business->id,
    'created_by' => $user->id,
    'category_id' => 1 // Assuming category 1 exists, or nullable
]);

echo "Created Expense ID: {$expense->id} on {$expense->expense_date}\n";

