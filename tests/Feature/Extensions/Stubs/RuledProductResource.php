<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;

class RuledProductResource extends ProductResource
{
    #[\Override]
    public function rules(): array
    {
        return ['price' => 'integer'];
    }

    #[\Override]
    public function createRules(): array
    {
        return ['price' => 'required'];
    }
}
