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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('original_filename');
            $table->bigInteger('original_size'); // 파일 크기 (bytes)
            $table->integer('duration')->nullable(); // 총 재생 시간 (초)
            $table->integer('original_width')->nullable(); // 원본 비디오 너비
            $table->integer('original_height')->nullable(); // 원본 비디오 높이
            $table->float('original_framerate')->nullable(); // 원본 프레임레이트
            $table->integer('original_bitrate')->nullable(); // 원본 비트레이트
            $table->string('thumbnail_path')->nullable();
            $table->string('scrubbing_sprite_path')->nullable();
            $table->integer('sprite_columns')->nullable();
            $table->integer('sprite_rows')->nullable();
            $table->integer('sprite_interval')->nullable(); // 스프라이트 간격 (초)
            $table->string('mime_type');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('meta_data')->nullable(); // 추가 메타데이터
                    $table->timestamps();
        $table->softDeletes();
        $table->integer('sort_order')->default(0);

            // 인덱스
            $table->index(['status']);
            $table->index(['user_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};