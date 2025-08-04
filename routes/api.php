<?php

use Illuminate\Support\Facades\Route;
use CmsOrbit\VideoField\Http\Controllers\VideoController;
use CmsOrbit\VideoField\Http\Controllers\VideoUploadController;

Route::prefix('api/video')->middleware(['web', 'auth'])->group(function () {
    // Video management routes
    Route::get('/', [VideoController::class, 'index'])->name('video.index');
    Route::get('/{video}', [VideoController::class, 'show'])->name('video.show');
    Route::post('/', [VideoController::class, 'store'])->name('video.store');
    Route::put('/{video}', [VideoController::class, 'update'])->name('video.update');
    Route::delete('/{video}', [VideoController::class, 'destroy'])->name('video.destroy');

    // Video upload routes
    Route::post('/upload/chunk', [VideoUploadController::class, 'uploadChunk'])->name('video.upload.chunk');
    Route::post('/upload/complete', [VideoUploadController::class, 'completeUpload'])->name('video.upload.complete');
    Route::post('/upload/cancel', [VideoUploadController::class, 'cancelUpload'])->name('video.upload.cancel');

    // Video streaming routes
    Route::get('/{video}/stream/{profile}', [VideoController::class, 'stream'])->name('video.stream');
    Route::get('/{video}/thumbnail', [VideoController::class, 'thumbnail'])->name('video.thumbnail');
    Route::get('/{video}/sprite', [VideoController::class, 'sprite'])->name('video.sprite');

    // Encoding management routes
    Route::post('/{video}/encode', [VideoController::class, 'encode'])->name('video.encode');
    Route::get('/{video}/encoding-status', [VideoController::class, 'encodingStatus'])->name('video.encoding.status');
});
