<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;

final readonly class PendingChecks
{
    /**
     * @internal
     *
     * @param  Collection<int, Model>  $rows
     */
    public function __construct(
        public Resource $resource,
        public string $ability,
        public Collection $rows,
        public ?Authenticatable $user,
    ) {}
}
