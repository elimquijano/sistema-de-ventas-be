<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SaleController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/receipts/{uuid}', [SaleController::class, 'showPublicReceipt'])->name('receipt.public');
