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
        Schema::table('videos', function (Blueprint $table) {
            $table->string('hls_manifest_path')->nullable()->after('scrubbing_sprite_path');
            $table->string('dash_manifest_path')->nullable()->after('hls_manifest_path');
            $table->json('abr_profiles')->nullable()->after('dash_manifest_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['hls_manifest_path', 'dash_manifest_path', 'abr_profiles']);
        });
    }
};