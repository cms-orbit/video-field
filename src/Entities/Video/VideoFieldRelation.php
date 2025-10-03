<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VideoFieldRelation extends MorphPivot
{
    protected $table = 'video_field_relations';
    protected $guarded = [];

    /**
     * Get the video that owns this relation.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * Get the related model.
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
