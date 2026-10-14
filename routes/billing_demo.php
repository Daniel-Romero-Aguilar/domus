<?php

use App\Http\Controllers\BillingDemoController;
use Illuminate\Support\Facades\Route;

// Include this file from routes/api.php. Laravel supplies the /api prefix.
Route::middleware(['auth:sanctum', 'throttle:30,1'])
    ->prefix('billing/demo')->group(function () {
    Route::get('/', [BillingDemoController::class, 'show']);
    Route::post('/verify', [BillingDemoController::class, 'verify']);
});
