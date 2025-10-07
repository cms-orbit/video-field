<?php

declare(strict_types=1);

namespace CmsOrbit\VideoField\Entities\Video\Layouts\Modals;

use CmsOrbit\VideoField\Entities\Video\VideoFieldRelation;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class VideoRelationsModal extends Table
{
    /**
     * @var string
     */
    public $target = 'videoRelationsModal.relations';

    /**
     * Get the table columns.
     */
    public function columns(): array
    {
        return [
            TD::make('model_type', __('Model Type'))
                ->render(function (VideoFieldRelation $relation) {
                    $modelType = $relation->getAttribute('model_type');
                    $parts = explode('\\', $modelType);
                    return end($parts);
                }),

            TD::make('model_id', __('Model ID'))
                ->render(function (VideoFieldRelation $relation) {
                    return $relation->getAttribute('model_id');
                }),

            TD::make('field_name', __('Field Name'))
                ->render(function (VideoFieldRelation $relation) {
                    return '<code>' . e($relation->getAttribute('field_name')) . '</code>';
                }),

            TD::make('sort_order', __('Sort Order'))
                ->width('100px')
                ->render(function (VideoFieldRelation $relation) {
                    return $relation->getAttribute('sort_order') ?? '-';
                }),

            TD::make(__('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(function (VideoFieldRelation $relation) {
                    $relationKey = base64_encode(json_encode([
                        'video_id' => $relation->getAttribute('video_id'),
                        'model_type' => $relation->getAttribute('model_type'),
                        'model_id' => $relation->getAttribute('model_id'),
                        'field_name' => $relation->getAttribute('field_name')]));
                    return Button::make(__('Detach'))
                        ->confirm(__('Are you sure you want to detach this relation?'))
                        ->method('detachRelation', [
                            'relationKey' => $relationKey
                        ])
                        ->class('btn btn-sm btn-danger');
                }),
        ];
    }
}


