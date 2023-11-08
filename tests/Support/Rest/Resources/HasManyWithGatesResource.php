<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

class HasManyWithGatesResource extends HasManyResource
{
    public function isGatingEnabled(): bool
    {
        return true;
    }
}
