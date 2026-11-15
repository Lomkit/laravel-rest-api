<?php

namespace Lomkit\Rest\Authorization;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;

final readonly class RowVersions
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
        private CollectedVersions $collected,
    ) {}

    public function add(Model $row, string $key, DateTimeInterface|int|string $version): void
    {
        $this->collected->add($row, $key, $version);
    }
}
