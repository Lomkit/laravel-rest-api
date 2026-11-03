<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Http\MutateResponse;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryController;

class RetryingCategoryController extends CategoryController
{
    #[\Override]
    public function mutate(MutateRequest $request): MutateResponse
    {
        parent::mutate($request);

        return parent::mutate($request);
    }
}
