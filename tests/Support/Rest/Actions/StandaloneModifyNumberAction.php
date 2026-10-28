<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Tests\Support\Models\Model;

class StandaloneModifyNumberAction extends ModifyNumberAction
{
    /**
     * Perform the action on the given models.
     *
     *
     * @return mixed
     */
    public function handle(array $fields, Collection $models)
    {
        Model::first()
            ->forceFill(['number' => $fields['number'] ?? 100000000])
            ->save();
    }
}
