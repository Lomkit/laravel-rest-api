<?php

namespace Lomkit\Rest\Validation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\ID;

final readonly class KeyList
{
    /**
     * @param  list<int|string>  $keys
     * @param  array<string, list<mixed>>  $rules
     */
    public function __construct(
        public array $keys,
        public array $rules,
        private ID $id,
    ) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function constrain(Builder $query): Builder
    {
        return $this->id->whereKeys($query, $this->keys);
    }
}
