<?php

use Illuminate\Support\Facades\Route;
use CmsOrbit\VideoField\Http\Controllers\VideoApiController;

/*
|--------------------------------------------------------------------------
| Video API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for the video field.
|
*/

Route::middleware(['web', 'auth'])->prefix('api/orbit-videos')->group(function () {
    Route::get('/search', [VideoApiController::class, 'search'])->name('orbit-videos.api.search');
    Route::get('/recent', [VideoApiController::class, 'recent'])->name('orbit-videos.api.recent');
    Route::post('/upload', [VideoApiController::class, 'upload'])->name('orbit-videos.api.upload');
    Route::post('/create-from-attachment', [VideoApiController::class, 'createFromAttachment'])->name('orbit-videos.api.create-from-attachment');
    Route::get('/{id}', [VideoApiController::class, 'show'])->name('orbit-videos.api.show');
});
