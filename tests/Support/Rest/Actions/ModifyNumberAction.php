<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;

class ModifyNumberAction extends Action
{
    public $queue = 'custom-queue';

    /**
     * Perform the action on the given models.
     *
     * @param array                          $fields
     * @param \Illuminate\Support\Collection $models
     *
     * @return mixed
     */
    public function handle(array $fields, Collection $models)
    {
        foreach ($models as $model) {
            /** @var Model $model */
            $model->forceFill(
                ['number' => $fields['number'] ?? 100000000]
            )
                ->save();
        }
    }

    /**
     * Called in an action failed.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function fields(\Lomkit\Rest\Http\Requests\RestRequest $request): array
    {
        return [
            'number' => [
                'numeric',
                'min:100',
            ],
        ];
    }
}
