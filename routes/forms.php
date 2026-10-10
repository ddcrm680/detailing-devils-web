<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Franchise\PrimeController;

Route::prefix('franchise')->group(function () {

    // Prime application page
    Route::get('/prime-form', [PrimeController::class, 'create'])
        ->name('prime.show');

    // Prime application submission
    Route::post('/prime', [PrimeController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('prime.submit');

});
