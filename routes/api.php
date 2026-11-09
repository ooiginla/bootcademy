<?php

use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\StorefrontController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Middleware\AuthenticateAcademyApiKey;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/paystack', PaystackWebhookController::class)->name('paystack.webhook');

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware([AuthenticateAcademyApiKey::class, 'throttle:60,1'])
    ->group(function (): void {
        Route::get('configuration', [ApplicationController::class, 'configuration'])->name('configuration');
        Route::post('applications', [ApplicationController::class, 'store'])->name('applications.store');
        Route::get('applications/status', [ApplicationController::class, 'show'])->name('applications.show');
        Route::get('store/catalog', [StorefrontController::class, 'catalog'])->name('store.catalog');
        Route::post('store/orders', [StorefrontController::class, 'order'])->name('store.orders.store');
    });
