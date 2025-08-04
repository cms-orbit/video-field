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
        Schema::create('video_field_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id');
            $table->morphs('model'); // model_type, model_id
            $table->string('field_name'); // 필드명 (opening, video1, video2 등)
            $table->integer('sort_order')->default(0); // 정렬 순서
            $table->timestamps();

            // 외래키
            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');

            // 인덱스 (morphs가 이미 model_type, model_id 인덱스를 생성함)
            $table->index(['video_id']);
            $table->index(['field_name']);
            $table->index(['sort_order']);

            // 유니크 제약 조건 (같은 모델, 필드에는 하나의 비디오만)
            $table->unique(['model_type', 'model_id', 'field_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_field_relations');
    }
};