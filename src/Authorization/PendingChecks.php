<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final readonly class PendingChecks
{
    /** @param Collection<int, Model> $rows */
    public function __construct(
        public string $ability,
        public Collection $rows,
        public ?Authenticatable $user,
    ) {}
}
