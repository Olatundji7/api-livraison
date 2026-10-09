<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdvertisementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DriverOrderController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\CartController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/advertisements', [AdvertisementController::class, 'index']);
Route::post('/payments/webhook/fedapay', [PaymentController::class, 'webhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/drivers/{driver}', [DriverController::class, 'show']);
    Route::get('/drivers/{driver}/location', [DriverController::class, 'location']);
    Route::get('/drivers/{driver}/locations', [DriverController::class, 'locations']);

    Route::middleware('role:client')->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart/items', [CartController::class, 'addItem']);
        Route::patch('/cart/items/{item}', [CartController::class, 'updateItem']);
        Route::delete('/cart/items/{item}', [CartController::class, 'removeItem']);
        Route::delete('/cart', [CartController::class, 'clear']);
        Route::post('/cart/checkout', [CartController::class, 'checkout']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/active', [OrderController::class, 'active']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::post('/orders/{order}/payment', [PaymentController::class, 'initiate']);
        Route::get('/payments/{reference}', [PaymentController::class, 'status']);
    });

    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/tracking', [OrderController::class, 'tracking']);

    Route::middleware('role:driver')->group(function () {
        Route::patch('/driver/status', [DriverController::class, 'updateStatus']);
        Route::post('/driver/location', [DriverController::class, 'updateLocation']);

        Route::prefix('driver')->group(function () {
            Route::get('/orders', [DriverOrderController::class, 'index']);
            Route::get('/orders/available', [DriverOrderController::class, 'available']);
            Route::post('/orders/{order}/accept', [DriverOrderController::class, 'accept']);
            Route::post('/orders/{order}/reject', [DriverOrderController::class, 'reject']);
            Route::post('/orders/{order}/start', [DriverOrderController::class, 'start']);
            Route::post('/orders/{order}/pickup', [DriverOrderController::class, 'pickup']);
            Route::post('/orders/{order}/deliver', [DriverOrderController::class, 'deliver']);
            Route::post('/orders/{order}/cancel', [DriverOrderController::class, 'cancel']);
        });
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/drivers', [AdminController::class, 'drivers']);
        Route::post('/drivers', [AdminController::class, 'storeDriver']);
        Route::put('/drivers/{driver}', [AdminController::class, 'updateDriver']);
        Route::get('/drivers/{driver}/locations', [AdminController::class, 'driverLocations']);
        Route::get('/orders', [AdminController::class, 'orders']);
        Route::post('/orders/{order}/assign', [AdminController::class, 'assignOrder']);
        Route::get('/products', [ProductController::class, 'adminIndex']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::get('/advertisements', [AdvertisementController::class, 'adminIndex']);
        Route::post('/advertisements', [AdvertisementController::class, 'store']);
        Route::put('/advertisements/{advertisement}', [AdvertisementController::class, 'update']);
        Route::delete('/advertisements/{advertisement}', [AdvertisementController::class, 'destroy']);
    });
});
