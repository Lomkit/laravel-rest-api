<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class RequiringPostResource extends PostResource
{
    #[\Override]
    public function rules(): array
    {
        return ['summary' => 'required'];
    }
}
