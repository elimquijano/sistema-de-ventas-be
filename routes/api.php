<?php

use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
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
});
