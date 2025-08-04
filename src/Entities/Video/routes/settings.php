<?php

use Illuminate\Support\Facades\Route;
use CmsOrbit\VideoField\Entities\Video\Screens\VideoListScreen;
use CmsOrbit\VideoField\Entities\Video\Screens\VideoEditScreen;

// 관리자 패널 라우트 (자동 등록됨)
Route::screen('videos', VideoListScreen::class)
    ->name('settings.entities.videos.index');

Route::screen('videos/create', VideoEditScreen::class)
    ->name('settings.entities.videos.create');

Route::screen('videos/{video}/edit', VideoEditScreen::class)
    ->name('settings.entities.videos.edit');
