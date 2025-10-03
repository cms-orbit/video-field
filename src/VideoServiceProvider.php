<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField;

use App\Console\Commands\BuildThemeScripts;
use App\Exceptions\ReservedAliasException;
use App\Lang\LoadLangTrait;
use App\Services\CmsHelper;
use App\Services\ThemePathRegister;
use Illuminate\Support\ServiceProvider;
use CmsOrbit\VideoField\Observers\VideoObserver;
use CmsOrbit\VideoField\Entities\Video\Video;
use CmsOrbit\VideoField\Console\Commands\VideoEncodeCommand;

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
            __DIR__.'/../config/orbit-video.php', 'orbit-video'
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
            __DIR__.'/../config/orbit-video.php' => config_path('orbit-video.php'),
        ], 'orbit-video-config');

        // 마이그레이션 발행
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'orbit-video-migrations');

        // 언어 파일 발행
        $this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang'),
        ], 'orbit-video-lang');

        // Register view namespace
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'cms-orbit-video');
        $this->loadViewsFrom(__DIR__ . '/Fields/VideoField', 'cms-orbit-video-field');

        // Register model observers
        Video::observe(VideoObserver::class);

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                VideoEncodeCommand::class,
            ]);
        }

        // 라이브러리 자동 배포
        $this->publishLibraries();
    }

    /**
     * 프론트엔드 경로 등록
     * @throws ReservedAliasException
     */
    protected function registerFrontendPaths(): void
    {
        // Register Stimulus controllers for Orchid fields
        if ($this->app->runningInConsole()) {
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

        BuildThemeScripts::registerController('video',__DIR__ . '/Fields/VideoField/js/video_controller.js');
    }

    /**
     * 라이브러리 자동 배포
     */
    public function publishLibraries(): void
    {
        $sourcePath = __DIR__ . '/../public';
        $targetPath = public_path('vendor/cms-orbit/video');

        // 디렉토리가 존재하지 않으면 생성
        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0755, true);
            mkdir($targetPath . '/js', 0755, true);
            mkdir($targetPath . '/css', 0755, true);
        }

        // 라이브러리 파일들 복사
        $this->copyLibraryFiles($sourcePath, $targetPath);
    }

    /**
     * 라이브러리 파일들 복사
     */
    protected function copyLibraryFiles(string $sourcePath, string $targetPath): void
    {
        // Copy files in js directory
        $jsSourceDir = $sourcePath . '/js';
        $jsTargetDir = $targetPath . '/js';
        if (is_dir($jsSourceDir)) {
            if (!is_dir($jsTargetDir)) {
                mkdir($jsTargetDir, 0755, true);
            }
            $jsFiles = glob($jsSourceDir . '/*.js');
            foreach ($jsFiles as $sourceFile) {
                $fileName = basename($sourceFile);
                $targetFile = $jsTargetDir . '/' . $fileName;
                if (!file_exists($targetFile) || filemtime($sourceFile) > filemtime($targetFile)) {
                    copy($sourceFile, $targetFile);
                }
            }
        }

        // Copy files in css directory
        $cssSourceDir = $sourcePath . '/css';
        $cssTargetDir = $targetPath . '/css';
        if (is_dir($cssSourceDir)) {
            if (!is_dir($cssTargetDir)) {
                mkdir($cssTargetDir, 0755, true);
            }
            $cssFiles = glob($cssSourceDir . '/*.css');
            foreach ($cssFiles as $sourceFile) {
                $fileName = basename($sourceFile);
                $targetFile = $cssTargetDir . '/' . $fileName;
                if (!file_exists($targetFile) || filemtime($sourceFile) > filemtime($targetFile)) {
                    copy($sourceFile, $targetFile);
                }
            }
        }
    }
}
