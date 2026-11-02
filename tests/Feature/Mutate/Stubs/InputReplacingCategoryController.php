<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryController;

class InputReplacingCategoryController extends CategoryController
{
    #[\Override]
    protected function beforeMutate(MutateRequest $request): void
    {
        $request->merge(['mutate' => [['operation' => 'create', 'attributes' => ['name' => 'Desks']]]]);
    }
}
