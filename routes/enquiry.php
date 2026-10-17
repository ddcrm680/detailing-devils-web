<?php

use App\Http\Controllers\EnquiryController;
use Illuminate\Support\Facades\Route;

// Homepage enquiry form
Route::post('/enquiry', [EnquiryController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('enquiry.store');
