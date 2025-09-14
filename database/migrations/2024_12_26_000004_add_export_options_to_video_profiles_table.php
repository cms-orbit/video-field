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
        Schema::table('video_profiles', function (Blueprint $table) {
            $table->boolean('export_progressive')->default(true)->after('dash_path');
            $table->boolean('export_hls')->default(true)->after('export_progressive');
            $table->boolean('export_dash')->default(true)->after('export_hls');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_profiles', function (Blueprint $table) {
            $table->dropColumn(['export_progressive', 'export_hls', 'export_dash']);
        });
    }
};
