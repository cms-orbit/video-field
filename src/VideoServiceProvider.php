<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField;

use App\Console\Commands\BuildThemeScripts;
use App\Exceptions\ReservedAliasException;
use App\Lang\LoadLangTrait;
use App\Services\CmsHelper;
use App\Services\ThemePathRegister;
use Illuminate\Support\ServiceProvider;
use CmsOrbit\VideoField\Console\Commands\VideoEncodeCommand;
use CmsOrbit\VideoField\Console\Commands\VideoThumbnailCommand;
use CmsOrbit\VideoField\Console\Commands\VideoSpriteCommand;
use CmsOrbit\VideoField\Console\Commands\VideoProcessAllCommand;

class VideoServiceProvider extends ServiceProvider
{
    use LoadLangTrait;

    /**
     * Register any application services.
     * @throws ReservedAliasException
     */
    public function register(): void
    {
        // 설정 파일 병합
        $this->mergeConfigFrom(
            __DIR__.'/../config/video.php', 'video'
        );

        // 마이그레이션 로드
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // API 라우트 로드
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // 프론트엔드 경로 등록
        $this->registerFrontendPaths();

        // 관리자 화면에 엔티티 자동 등록
        CmsHelper::addEntityPath(
            "CmsOrbit\\VideoField\\Entities",
            __DIR__ . "/Entities"
        );
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        // 다국어 파일 로드 (LoadLangTrait 사용)
        $this->loadJsonLang(__DIR__ . '/../resources/lang');

        // 설정 파일 발행
        $this->publishes([
            __DIR__.'/../config/video.php' => config_path('video.php'),
        ], 'video-config');

        // 마이그레이션 발행
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'video-migrations');

        // 언어 파일 발행
        $this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang'),
        ], 'video-lang');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                VideoEncodeCommand::class,
                VideoThumbnailCommand::class,
                VideoSpriteCommand::class,
                VideoProcessAllCommand::class,
            ]);
        }

        // Register view namespace
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'video-field');
    }

    /**
     * 프론트엔드 경로 등록
     * @throws ReservedAliasException
     */
    protected function registerFrontendPaths(): void
    {
        // Vite 별칭 등록 - 테마에서 @orbit/video로 접근 가능
        $frontendPath = new ThemePathRegister(
            '@orbit/video',
            __DIR__ . '/../resources/js'
        );
        BuildThemeScripts::registerPath($frontendPath);

        // Tailwind CSS 경로 등록
        BuildThemeScripts::registerTailwindBase(
            __DIR__ . '/../resources/js/**/**/*.vue'
        );
    }
}
