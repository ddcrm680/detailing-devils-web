<?php

use App\Http\Controllers\DeployController;
use Illuminate\Support\Facades\Route;

Route::get('/deploy', DeployController::class)
    ->name('deploy');
