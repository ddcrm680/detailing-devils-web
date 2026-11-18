<?php

use App\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

Route::get('/locate-a-store', [StudioController::class, 'index'])->name('studios.index');
Route::get('/detailing-studios/{slug}', [StudioController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:[.-][a-z0-9]+)*')
    ->name('studios.show');
Route::get('/studios/{slug}', [StudioController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:[.-][a-z0-9]+)*')
    ->name('studios.legacy');
Route::view('/studio-tour', 'studios.tour')
    ->defaults('_seo', [
        'variants' => [
            'fofo' => ['model' => 'fofo'],
            'prime' => ['model' => 'prime'],
        ],
    ])
    ->name('studios.tour');
