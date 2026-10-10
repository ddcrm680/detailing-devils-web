<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// Home page
Route::get('/', HomeController::class)->name('home');

// Franchise form routes
require __DIR__ . '/forms.php';

// OTP verification routes
require __DIR__ . '/otp.php';

// Enquiry + Admin  enquiry routes
require __DIR__ . '/enquiry.php';
