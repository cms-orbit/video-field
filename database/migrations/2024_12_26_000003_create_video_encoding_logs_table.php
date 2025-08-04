<?php

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
        Schema::create('video_encoding_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_profile_id');
            $table->enum('status', ['started', 'progress', 'completed', 'error']);
            $table->text('message')->nullable(); // 로그 메시지
            $table->integer('progress')->default(0); // 진행률 (0-100)
            $table->text('ffmpeg_command')->nullable(); // 실행된 FFmpeg 명령어
            $table->text('error_output')->nullable(); // 에러 출력
            $table->integer('processing_time')->nullable(); // 처리 시간 (초)
            $table->timestamp('created_at')->useCurrent();

            // 외래키
            $table->foreign('video_profile_id')->references('id')->on('video_profiles')->onDelete('cascade');

            // 인덱스
            $table->index(['video_profile_id']);
            $table->index(['status']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_encoding_logs');
    }
};