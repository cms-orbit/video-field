<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use CmsOrbit\VideoField\Entities\Video\VideoProfile;

class VideoEncodingLog extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'video_encoding_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'video_profile_id',
        'status',
        'message',
        'progress',
        'ffmpeg_command',
        'error_output',
        'processing_time',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'progress' => 'integer',
        'processing_time' => 'integer',
    ];

    /**
     * Indicates if the model should be timestamped.
     * We only need created_at for logs.
     */
    const UPDATED_AT = null;

    /**
     * Get the video profile that owns this log.
     */
    public function videoProfile(): BelongsTo
    {
        return $this->belongsTo(VideoProfile::class);
    }

    /**
     * Get the video through profile.
     */
    public function video()
    {
        return $this->videoProfile->video();
    }

    /**
     * Get status badge color.
     */
    public function getStatusColor(): string
    {
        return match($this->status) {
            'started' => 'info',
            'progress' => 'warning',
            'completed' => 'success',
            'error' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get human readable processing time.
     */
    public function getReadableProcessingTime(): string
    {
        if (!$this->processing_time) {
            return 'N/A';
        }

        $seconds = $this->processing_time;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Scope for filtering by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering recent logs.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
