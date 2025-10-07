<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('video_watch_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\CmsOrbit\VideoField\Entities\Video\Video::class)
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->morphs('watcher');
            $table->string('session_id')->nullable()->index();
            $table->double('duration')->default(0)->nullable()->comment('비디오 총 길이 (초)');
            $table->double('percent')->default(0)->nullable()->comment('시청 진행율 (0-100)');
            $table->double('seconds')->default(0)->nullable()->comment('현재 시청 지점 (초)');
            $table->double('played')->default(0)->nullable()->comment('마지막 시청 시간 (초) - 빨리감기 제한용');
            $table->boolean('is_complete')->default(false)->index()->comment('시청 완료 여부');
            $table->timestamps();

            // 인덱스
            $table->index(['video_id', 'watcher_id', 'watcher_type']);
            $table->index(['video_id', 'session_id']);
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_watch_histories');
    }
};

