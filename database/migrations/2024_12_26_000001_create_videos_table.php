<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchid\Attachment\Models\Attachment;

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

            $table->foreignIdFor(\App\Settings\Entities\User\User::class);
            $table->foreignIdFor(Attachment::class,'original_file_id');
            // ['uploading','upload_failed','uploaded','pending', 'processing', 'completed', 'failed']
            $table->string('status')->default('uploading')->index();

            $table->decimal('duration',10,3)->nullable(); // 총 재생 시간 (초)
            $table->integer('original_width')->nullable(); // 원본 비디오 너비
            $table->integer('original_height')->nullable(); // 원본 비디오 높이
            $table->float('original_framerate')->nullable(); // 원본 프레임레이트
            $table->integer('original_bitrate')->nullable(); // 원본 비트레이트
            $table->string('thumbnail_path')->nullable();
            $table->string('scrubbing_sprite_path')->nullable();

            $table->string('hls_manifest_path')->nullable();
            $table->string('dash_manifest_path')->nullable();
            $table->json('abr_profiles')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->integer('sort_order')->default(0);
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
