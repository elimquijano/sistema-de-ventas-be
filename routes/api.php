<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CashRegisterController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WhatsAppController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    // Route::post('register', [AuthController::class, 'register']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Dashboard routes
    Route::prefix('dashboard')->group(function () {
        Route::get('stats', [DashboardController::class, 'stats']);
        Route::get('charts/{type}', [DashboardController::class, 'chartData']);
        Route::get('recent-activity', [DashboardController::class, 'recentActivity']);
    });

    // Modules routes
    Route::get('modules/tree', [ModuleController::class, 'tree']);
    Route::get('modules/menu', [ModuleController::class, 'menu']);
    Route::get('modules/route-config', [ModuleController::class, 'getRouteConfig']);
    Route::apiResource('modules', ModuleController::class);

    // Permissions routes
    Route::get('permissions/module/{moduleId}', [PermissionController::class, 'byModule']);
    Route::apiResource('permissions', PermissionController::class);

    // Roles routes
    Route::apiResource('roles', RoleController::class);
    Route::post('roles/{role}/permissions', [RoleController::class, 'assignPermissions']);

    // Users routes
    Route::apiResource('users', UserController::class);
    Route::patch('users/{user}/status', [UserController::class, 'updateStatus']);
    Route::post('users/{user}/roles', [UserController::class, 'assignRoles']);

    // Notifications routes
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::apiResource('notifications', NotificationController::class)->only(['index', 'destroy']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    // Negocios
    Route::get('/businesses/{business}/dashboard', [BusinessController::class, 'dashboard']);
    Route::apiResource('businesses', BusinessController::class);

    // Categorías
    Route::apiResource('categories', CategoryController::class);

    // Inventario
    // Búsqueda de productos para autocompletado
    Route::get('products/search', [ProductController::class, 'search']);
    Route::patch('products/{product}/stock', [ProductController::class, 'updateStock']);
    Route::get('products/low-stock', [ProductController::class, 'getLowStock']);
    Route::post('products/{product}', [ProductController::class, 'update'])->name('products.update.post');
    Route::apiResource('products', ProductController::class);
    Route::patch('services/{service}/status', [ServiceController::class, 'updateStatus']);
    Route::apiResource('services', ServiceController::class);

    // Caja Registradora
    Route::get('/cash-registers', [CashRegisterController::class, 'index']);
    Route::get('/cash-registers/current', [CashRegisterController::class, 'current']);
    Route::post('/cash-registers', [CashRegisterController::class, 'store']);
    Route::post('/cash-registers/{cashRegister}/add-inflow', [CashRegisterController::class, 'addInflow']);
    Route::post('/cash-registers/{cashRegister}/close', [CashRegisterController::class, 'close']);
    Route::get('/cash-registers/{cashRegister}/report', [CashRegisterController::class, 'report']);

    // Ventas y Créditos
    Route::get('sales/daily', [SaleController::class, 'getDailySales']); // MODIFICADO
    Route::get('sales/monthly/{year}/{month}', [SaleController::class, 'getMonthlySales']);
    Route::post('sales/quick-order', [SaleController::class, 'quickOrder']);
    Route::post('sales/{sale}/confirm-delivery', [SaleController::class, 'confirmDelivery']);
    Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel']);
    Route::post('sales/{sale}/whatsapp-resend', [WhatsAppController::class, 'resendSaleMessage']);
    Route::apiResource('sales', SaleController::class);

    // Clients
    Route::apiResource('clients', ClientController::class);

    Route::post('/credits/{credit}/payment', [CreditController::class, 'addPayment']);
    Route::get('credits/pending', [CreditController::class, 'getPending']); // Nueva ruta
    Route::apiResource('credits', CreditController::class)->except(['store', 'destroy']);

    // Gastos
    Route::get('expenses/category/{categoryId}', [ExpenseController::class, 'getByCategory']); // Nueva ruta
    Route::apiResource('expenses', ExpenseController::class);

    // Préstamos
    Route::post('loans/{loan}/payment', [LoanController::class, 'addPayment']); // Changed from 'return'
    Route::get('loans/pending', [LoanController::class, 'getPending']);
    Route::apiResource('loans', LoanController::class)->except(['update']); // Exclude default update if we have a custom one
    Route::post('loans/{loan}', [LoanController::class, 'update']); // For handling form-data if needed, or just use PUT

    // Subida de archivos
    Route::post('/files/upload', [FileController::class, 'upload']);

    // Compras
    Route::apiResource('purchases', PurchaseController::class);

    // Generar recibo de venta en PDF
    Route::get('sales/{sale}/receipt', [SaleController::class, 'generateReceipt']);
});
