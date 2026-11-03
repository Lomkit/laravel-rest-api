<?php

namespace Lomkit\Rest\Contracts;

use Illuminate\Database\Eloquent\Model;

interface OwnerRelation
{
    /** @return array<string, mixed> */
    public function ownedAttributes(Model $model): array;
}
