<?php

use App\Http\Controllers\SeoAdmin\MetadataController;
use App\Http\Controllers\SeoAdmin\PublicationController;
use App\Http\Controllers\SeoAdmin\SessionController;
use App\Http\Middleware\SeoAdminReady;
use Illuminate\Support\Facades\Route;

// The normal web stack supplies sessions, cookies, validation errors and CSRF protection.
Route::prefix('admin')
    ->name('admin.')
    ->middleware(SeoAdminReady::class)
    ->group(function () {
        Route::get('/login', [SessionController::class, 'create'])->name('login');
        Route::post('/login', [SessionController::class, 'store'])->name('login.store');
        Route::middleware(['auth:web', 'can:manage-seo'])->group(function () {
            Route::get('/', fn () => redirect()->route('admin.seo.index'))->name('index');
            Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
            Route::prefix('seo')
                ->name('seo.')
                ->group(function () {
                    Route::get('/', [MetadataController::class, 'index'])->name('index');
                    Route::get('/pages/{page}', [MetadataController::class, 'edit'])->name('edit');
                    Route::post('/pages/{page}/draft', [MetadataController::class, 'save'])->name('draft');
                    Route::post('/pages/{page}/preview', [MetadataController::class, 'preview'])->name('preview');
                    Route::post('/images', [MetadataController::class, 'upload'])->name('images.upload');
                    Route::get('/images/{image}', [MetadataController::class, 'media'])
                        ->whereUuid('image')
                        ->name('images.show');
                    Route::post('/images/{image}/remove', [MetadataController::class, 'removeImage'])
                        ->whereUuid('image')
                        ->name('images.remove');
                    Route::post('/revisions/{revision}/publish', [PublicationController::class, 'publish'])
                        ->whereNumber('revision')
                        ->name('publish');
                    Route::post('/revisions/{revision}/rollback', [PublicationController::class, 'rollback'])
                        ->whereNumber('revision')
                        ->name('rollback');
                });
        });
    });
