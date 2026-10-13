<?php

use App\Http\Controllers\Franchise\FofoController;
use App\Http\Controllers\Franchise\PrimeController;
use Illuminate\Support\Facades\Route;

Route::prefix('franchise')->group(function () {

    Route::get('/fofo-form', [FofoController::class, 'create'])
        ->name('fofo.show');

    Route::post('/fofo', [FofoController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('fofo.submit');

    // Prime application page
    Route::get('/prime-form', [PrimeController::class, 'create'])
        ->name('prime.show');

    // Prime application submission
    Route::post('/prime', [PrimeController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('prime.submit');

});
