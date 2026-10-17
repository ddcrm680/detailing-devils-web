<?php

use App\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

Route::get('/locate-a-store', [StudioController::class, 'index'])->name('studios.index');
Route::view('/studio-tour', 'studios.tour')->name('studios.tour');
