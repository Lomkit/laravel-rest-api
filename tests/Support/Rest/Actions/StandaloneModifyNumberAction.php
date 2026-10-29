<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Support\Collection;
use Lomkit\Rest\Tests\Support\Models\Model;

class StandaloneModifyNumberAction extends ModifyNumberAction
{
    public function handle(array $fields, Collection $models): void
    {
        Model::first()
            ->forceFill(['number' => $fields['number'] ?? 100000000])
            ->save();
    }
}
