<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\Plan\QueryAdditions;

final readonly class AugmentationContext
{
    /**
     * @internal
     *
     * @param  list<string>  $gates
     */
    public function __construct(
        private string $extension,
        public Resource $resource,
        public ?Authenticatable $user,
        public array $gates,
        private QueryAdditions $additions,
    ) {}

    public function column(string $column): void
    {
        $this->additions->column($this->extension, $column);
    }

    /** @param Closure(QueryBuilder): mixed|QueryBuilder|EloquentBuilder<Model> $subquery */
    public function select(string $alias, Closure|QueryBuilder|EloquentBuilder $subquery): string
    {
        return $this->additions->select($this->extension, $alias, $subquery);
    }

    /** @param (Closure(mixed): mixed)|null $constraint */
    public function with(string $relation, ?Closure $constraint = null): void
    {
        $this->additions->with($this->extension, $relation, $constraint);
    }
}
