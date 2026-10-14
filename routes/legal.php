<?php

use Illuminate\Support\Facades\Route;

Route::view('/terms-of-use', 'legal.terms')->name('legal.terms');
Route::view('/privacy-policy', 'legal.privacy')->name('legal.privacy');
Route::view('/refund-policy', 'legal.returns')->name('legal.returns');
Route::view('/cancellation-policy', 'legal.cancellation')->name('legal.cancellation');
