<?php

use Illuminate\Support\Facades\Route;
use CmsOrbit\VideoField\Http\Controllers\VideoApiController;
use CmsOrbit\VideoField\Http\Controllers\VideoPlayerApiController;

/*
|--------------------------------------------------------------------------
| Video API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for the video field.
|
*/

// Video Management API (관리자용)
Route::middleware(['web', 'auth'])->prefix('api/orbit-videos')->group(function () {
    Route::get('/search', [VideoApiController::class, 'search'])->name('orbit-videos.api.search');
    Route::get('/recent', [VideoApiController::class, 'recent'])->name('orbit-videos.api.recent');
    Route::post('/upload', [VideoApiController::class, 'upload'])->name('orbit-videos.api.upload');
    Route::post('/create-from-attachment', [VideoApiController::class, 'createFromAttachment'])->name('orbit-videos.api.create-from-attachment');
});

// Video Player API (프론트엔드용)
Route::middleware(['web'])->prefix('api/orbit-video-player')->group(function () {
    // 비디오 정보 조회
    Route::get('/{id}', [VideoPlayerApiController::class, 'show'])->name('orbit-video-player.api.show');
    
    // 재생 이벤트
    Route::post('/{id}/play', [VideoPlayerApiController::class, 'recordPlay'])->name('orbit-video-player.api.play');
    Route::post('/{id}/pause', [VideoPlayerApiController::class, 'recordPause'])->name('orbit-video-player.api.pause');
    Route::post('/{id}/progress', [VideoPlayerApiController::class, 'recordProgress'])->name('orbit-video-player.api.progress');
    Route::post('/{id}/complete', [VideoPlayerApiController::class, 'recordComplete'])->name('orbit-video-player.api.complete');
    
    // 재생 위치
    Route::get('/{id}/position', [VideoPlayerApiController::class, 'position'])->name('orbit-video-player.api.position.get');
    Route::post('/{id}/position', [VideoPlayerApiController::class, 'position'])->name('orbit-video-player.api.position.save');
    
    // 조회수
    Route::post('/{id}/view', [VideoPlayerApiController::class, 'incrementView'])->name('orbit-video-player.api.view');
    
    // 문제 리포트
    Route::post('/{id}/report-issue', [VideoPlayerApiController::class, 'reportIssue'])->name('orbit-video-player.api.report-issue');
    
    // 분석 데이터 (관리자만)
    Route::get('/{id}/analytics', [VideoPlayerApiController::class, 'analytics'])
        ->middleware('auth')
        ->name('orbit-video-player.api.analytics');
});
