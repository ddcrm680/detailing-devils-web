<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Franchise\OtpController;

Route::prefix('otp')
    ->name('otp.')
    ->group(function () {

        Route::post('/send', [OtpController::class, 'send'])
            ->middleware('throttle:3,1')
            ->name('send');

        Route::post('/verify', [OtpController::class, 'verify'])
            ->middleware('throttle:10,1')
            ->name('verify');

    });
