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
     */
    public function fields(RestRequest $request): array
    {
        return [
            'number' => [
                'numeric',
                'min:100',
            ],
        ];
    }
}
