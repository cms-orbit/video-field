<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoFieldRelation extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'video_field_relations';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'video_id',
        'model_type',
        'model_id',
        'field_name',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'video_id' => 'integer',
        'model_id' => 'integer',
        'sort_order' => 'integer',
    ];

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
    public function model()
    {
        return $this->morphTo();
    }
} 