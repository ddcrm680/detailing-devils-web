<?php

use App\Http\Controllers\EmailValidationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Public XML stays stateless even though it is declared in the web route file.
Route::get('/sitemap.xml', SitemapController::class)->withoutMiddleware('web')->name('sitemap');

// Administration inherits the web middleware once from this route file.
require __DIR__.'/admin.php';

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

// Homepage enquiry route
require __DIR__.'/enquiry.php';

// Website legal policies
require __DIR__.'/legal.php';

// Studio locator
require __DIR__.'/studios.php';
