<?php

namespace Lomkit\Rest\Validation;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\ID;

use function Illuminate\Support\enum_value;

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
        private ?KeyLookup $lookup = null,
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

    /** @return Collection<int, Model> */
    public function rows(): Collection
    {
        return new Collection($this->lookup?->rows() ?? []);
    }

    /** @return Closure(Model): ?int */
    public function positions(): Closure
    {
        $positions = array_flip(array_map($this->id->normalizeKey(...), $this->keys));

        return function (Model $row) use ($positions): ?int {
            $key = $this->id->parseKey(enum_value($row->getKey()));

            return $key === null ? null : $positions[$this->id->normalizeKey($key)] ?? null;
        };
    }
}
