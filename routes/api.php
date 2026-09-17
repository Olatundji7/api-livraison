<?php

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\DelivererAdminController;
use App\Http\Controllers\Api\Admin\OrderAdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DelivererController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // --- Authentification (publique) ---
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // --- Public ---
    Route::get('/services', [ServiceController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // --- Espace Client ---
        Route::middleware('role:client')->group(function () {
            Route::post('/orders', [OrderController::class, 'store']);
            Route::get('/deliverers/nearby', [OrderController::class, 'nearbyDeliverers']);
        });

        // Consultable par client (propriétaire), livreur assigné ou admin — vérifié dans le contrôleur
        Route::get('/orders/{order}', [OrderController::class, 'show']);

        // --- Espace Livreur ---
        Route::middleware('role:livreur')->group(function () {
            Route::patch('/deliverer/availability', [DelivererController::class, 'availability']);
            Route::get('/deliverer/orders/available', [DelivererController::class, 'availableOrders']);
            Route::post('/deliverer/orders/{order}/accept', [DelivererController::class, 'accept']);
            Route::patch('/deliverer/orders/{order}/status', [DelivererController::class, 'updateStatus']);
            Route::post('/deliverer/location', [DelivererController::class, 'updateLocation']);
            Route::get('/deliverer/orders/{order}/invoice', [DelivererController::class, 'invoice']);
        });

        // --- Espace Admin ---
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('/deliverers/pending', [DelivererAdminController::class, 'pending']);
            Route::patch('/deliverers/{deliverer}/validate', [DelivererAdminController::class, 'validateDeliverer']);
            Route::get('/orders', [OrderAdminController::class, 'index']);
            Route::post('/orders/{order}/assign', [OrderAdminController::class, 'assign']);
            Route::post('/admins', [AdminController::class, 'store']);
        });
    });
});
