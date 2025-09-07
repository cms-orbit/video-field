<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Video Storage Configuration
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'disk' => env('VIDEO_STORAGE_DISK', env('MEDIA_DISK', 'public')),
        'video_path' => env('VIDEO_STORAGE_PATH', 'videos/{videoId}'),
        'thumbnails_path' => env('VIDEO_THUMBNAILS_PATH', 'videos/{videoId}/thumbnails'),
        'sprites_path' => env('VIDEO_SPRITES_PATH', 'videos/{videoId}/sprites'),
    ],

    /*
    |--------------------------------------------------------------------------
    | FFmpeg Configuration
    |--------------------------------------------------------------------------
    */
    'ffmpeg' => [
        'binary_path' => env('FFMPEG_BINARY_PATH', 'ffmpeg'),
        'ffprobe_path' => env('FFPROBE_BINARY_PATH', 'ffprobe'),
        'timeout' => env('FFMPEG_TIMEOUT', 3600), // 1 hour
        'threads' => env('FFMPEG_THREADS', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Configuration
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'max_file_size' => env('VIDEO_MAX_FILE_SIZE', 5368709120), // 5GB in bytes
        'allowed_extensions' => ['mp4', 'mov', 'avi', 'mkv', 'webm', 'flv', 'm4v'],
        'allowed_mime_types' => [
            'video/mp4',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-matroska',
            'video/webm',
            'video/x-flv',
            'video/x-m4v',
        ],
        'chunk_size' => env('VIDEO_CHUNK_SIZE', 1048576), // 1MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Video Profiles
    |--------------------------------------------------------------------------
    */
    'default_profiles' => [
        '4K@60fps' => [
            'width' => 3840,
            'height' => 2160,
            'framerate' => 60,
            'bitrate' => '15M',
            'profile' => 'main10',
            'level' => '5.1',
            'codec' => 'libx264',
        ],
        '4K@30fps' => [
            'width' => 3840,
            'height' => 2160,
            'framerate' => 30,
            'bitrate' => '10M',
            'profile' => 'main10',
            'level' => '5.0',
            'codec' => 'libx264',
        ],
        'FHD@60fps' => [
            'width' => 1920,
            'height' => 1080,
            'framerate' => 60,
            'bitrate' => '12M',
            'profile' => 'main',
            'level' => '4.1',
            'codec' => 'libx264',
        ],
        'FHD@30fps' => [
            'width' => 1920,
            'height' => 1080,
            'framerate' => 30,
            'bitrate' => '8M',
            'profile' => 'main',
            'level' => '4.0',
            'codec' => 'libx264',
        ],
        'HD@30fps' => [
            'width' => 1280,
            'height' => 720,
            'framerate' => 30,
            'bitrate' => '4M',
            'profile' => 'main',
            'level' => '3.1',
            'codec' => 'libx264',
        ],
        'SD@30fps' => [
            'width' => 640,
            'height' => 480,
            'framerate' => 30,
            'bitrate' => '2M',
            'profile' => 'main',
            'level' => '3.0',
            'codec' => 'libx264',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Thumbnail Configuration
    |--------------------------------------------------------------------------
    */
    'thumbnails' => [
        'quality' => 100,
        'format' => 'jpeg',
        'time_position' => '00:00:05', // 5 seconds into video
    ],

    /*
    |--------------------------------------------------------------------------
    | Sprite Configuration (for video scrubbing)
    |--------------------------------------------------------------------------
    */
    'sprites' => [
        'enabled' => true,
        'width' => 160,
        'height' => 90,
        'columns' => 10,
        'rows' => 10,
        'interval' => 10, // seconds between frames
        'quality' => 70,
        'format' => 'jpeg',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('QUEUE_CONNECTION', 'redis'),
        'queue_name' => env('VIDEO_QUEUE_NAME', 'default'),
        'max_tries' => env('VIDEO_MAX_TRIES', 3),
        'retry_delay' => env('VIDEO_RETRY_DELAY', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        'auto_cleanup' => env('VIDEO_AUTO_CLEANUP', true),
        'temp_file_ttl' => env('VIDEO_TEMP_FILE_TTL', 86400), // 24 hours
        'failed_job_ttl' => env('VIDEO_FAILED_JOB_TTL', 604800), // 1 week
    ],
];
