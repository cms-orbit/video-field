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
        Schema::create('video_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('video_id');
            $table->string('field'); // 필드명 (opening, video1, video2 등)
            $table->string('profile'); // 프로파일명 (4K@60fps, FHD@30fps 등)
            $table->string('path')->nullable(); // 인코딩된 파일 경로
            $table->string('hls_path')->nullable(); // HLS 플레이리스트 경로
            $table->string('dash_path')->nullable(); // DASH 매니페스트 경로
            $table->boolean('encoded')->default(false); // 인코딩 완료 여부
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->bigInteger('file_size')->nullable(); // 파일 크기 (bytes)
            $table->integer('width')->nullable(); // 가로 해상도
            $table->integer('height')->nullable(); // 세로 해상도
            $table->integer('framerate')->nullable(); // 프레임레이트
            $table->string('bitrate')->nullable(); // 비트레이트
            $table->timestamps();

            // 외래키
            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');

            // 인덱스
            $table->index(['video_id', 'field']);
            $table->index(['video_id', 'profile']);
            $table->index(['encoded']);
            $table->index(['profile']);

            // 유니크 제약 조건 (같은 비디오, 필드, 프로파일 조합은 유일해야 함)
            $table->unique(['video_id', 'field', 'profile']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_profiles');
    }
};