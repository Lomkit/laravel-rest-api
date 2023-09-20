<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Lomkit\Rest\Concerns\Resource\DisableAutomaticGates;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;

class HasManyWithGatesResource extends HasManyResource
{
    public function isAutomaticGatingEnabled(): bool
    {
        return true;
    }
}
