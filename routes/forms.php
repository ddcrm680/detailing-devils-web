<?php

use App\Http\Controllers\Franchise\FofoController;
use App\Http\Controllers\Franchise\PrimeController;
use App\Http\Controllers\Franchise\XclusiveController;
use Illuminate\Support\Facades\Route;

Route::get('/franchise-owned-franchise-operated-model', [FofoController::class, 'create'])
    ->name('fofo.show');

Route::get('/detailing-devils-prime-form', [PrimeController::class, 'create'])
    ->name('prime.show');

Route::get('/x-clusive-franchise-form', [XclusiveController::class, 'create'])
    ->name('xclusive.show');

Route::prefix('franchise')->group(function () {

    Route::post('/fofo', [FofoController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('fofo.submit');

    // Prime application submission
    Route::post('/prime', [PrimeController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('prime.submit');

    Route::post('/xclusive', [XclusiveController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('xclusive.submit');

});
