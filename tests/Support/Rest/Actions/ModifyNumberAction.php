<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Fields\Number;

class ModifyNumberAction extends Action
{
    public $queue = 'custom-queue';

    public function handle(ActionFields $fields, Collection $models): void
    {
        foreach ($models as $model) {
            /** @var Model $model */
            $model->forceFill(
                ['number' => $fields->number ?? 100000000]
            )
                ->save();
        }
    }

    /**
     * Called in an action failed.
     */
    public function fields(): array
    {
        return [
            Number::make('number')->rules('min:100'),
        ];
    }
}
