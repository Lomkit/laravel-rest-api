<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\TextSearch;

class UnsearchableCategoryResource extends CategoryResource
{
    #[\Override]
    public function text(): TextSearch
    {
        return Contains::make();
    }
}
