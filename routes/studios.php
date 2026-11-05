<?php

use App\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

Route::get('/locate-a-store', [StudioController::class, 'index'])->name('studios.index');
Route::view('/studio-tour', 'studios.tour')
    ->defaults('_seo', [
        'variants' => [
            'fofo' => ['model' => 'fofo'],
            'prime' => ['model' => 'prime'],
        ],
    ])
    ->name('studios.tour');
