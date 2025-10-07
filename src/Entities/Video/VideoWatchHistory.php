<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class VideoWatchHistory extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'video_watch_histories';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'video_id',
        'watcher_id',
        'watcher_type',
        'session_id',
        'duration',
        'percent',
        'seconds',
        'played',
        'is_complete',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'duration' => 'double',
        'percent' => 'double',
        'seconds' => 'double',
        'played' => 'double',
        'is_complete' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the video that owns the watch history.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * Get the watcher (user) that owns the watch history.
     */
    public function watcher()
    {
        return $this->morphTo();
    }

    /**
     * Update watch progress.
     */
    public function updateProgress(float $currentTime, float $duration): void
    {
        $this->setAttribute('duration', $duration);
        $this->setAttribute('seconds', $currentTime);
        
        // played는 현재 시청 지점이 이전 played보다 크면 업데이트
        if ($currentTime > $this->getAttribute('played')) {
            $this->setAttribute('played', $currentTime);
        }
        
        // 진행율 계산
        $percent = $duration > 0 ? ($this->getAttribute('played') / $duration) * 100 : 0;
        $this->setAttribute('percent', min(100, $percent));
        
        // 시청 완료 여부 확인
        $completionThreshold = config('orbit-video.player.completion_threshold', 0.9) * 100;
        if ($this->getAttribute('percent') >= $completionThreshold) {
            $this->setAttribute('is_complete', true);
        }
        
        $this->save();
    }

    /**
     * Check if playback should be restricted at given time (lecture mode).
     */
    public function canSeekTo(float $time): bool
    {
        return $time <= $this->getAttribute('played');
    }

    /**
     * Get the maximum seekable time (for lecture mode).
     */
    public function getMaxSeekableTime(): float
    {
        return $this->getAttribute('played');
    }
}

