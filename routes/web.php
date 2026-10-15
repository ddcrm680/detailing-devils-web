<?php

use App\Http\Controllers\EmailValidationController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// Home page
Route::get('/', HomeController::class)->name('home');

// Shared email deliverability check
Route::post('/email/validate', EmailValidationController::class)
    ->middleware('throttle:5,1')
    ->name('email.validate');

// Franchise form routes
require __DIR__.'/forms.php';

// Shared country, state and city lists
require __DIR__.'/locations.php';

// OTP verification routes
require __DIR__.'/otp.php';

// Enquiry + Admin  enquiry routes
require __DIR__.'/enquiry.php';

// Website legal policies
require __DIR__.'/legal.php';

// Studio locator
require __DIR__.'/studios.php';
