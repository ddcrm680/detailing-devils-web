<?php

use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\HomeController;
use App\Http\Middleware\AdminBasicAuth;
use Illuminate\Support\Facades\Route;

// Home page
Route::get('/', HomeController::class)->name('home');

// Contact / franchise form
Route::post('/enquiry', [EnquiryController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('enquiry.store');

// Enquiries list (username and password are set in the .env file)
Route::get('/admin/enquiries', [EnquiryController::class, 'index'])
    ->middleware(AdminBasicAuth::class)
    ->name('enquiry.index');
