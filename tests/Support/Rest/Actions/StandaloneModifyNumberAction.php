<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;

class StandaloneModifyNumberAction extends ModifyNumberAction
{
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
        \Lomkit\Rest\Tests\Support\Models\Model::first()
            ->forceFill(['number' => $fields['number'] ?? 100000000])
            ->save();
    }
}
