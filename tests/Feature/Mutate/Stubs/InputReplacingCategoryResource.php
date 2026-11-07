<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;

class InputReplacingCategoryResource extends CategoryResource
{
    #[\Override]
    public function mutatingAll(array $entries): void
    {
        $this->request()->merge(['mutate' => [['operation' => 'create', 'attributes' => ['name' => 'Desks']]]]);
    }
}
