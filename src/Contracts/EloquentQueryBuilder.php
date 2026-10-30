<?php

namespace Lomkit\Rest\Contracts;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

interface EloquentQueryBuilder extends QueryBuilder
{
    /**
     * @param  array<string, mixed>  $parameters
     * @return EloquentBuilder<Model>
     */
    #[\Override]
    public function search(array $parameters = []);

    /** @return EloquentBuilder<Model> */
    #[\Override]
    public function toBase();

    /** @return $this */
    public function disableDefaultLimit(bool $disable = true);

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, list<int|string>>
     */
    public function mutate(array $parameters = []): array;

    /**
     * @param  array<string, mixed>  $mutation
     * @param  array<string, mixed>  $attributes
     */
    public function applyMutation(array $mutation = [], array $attributes = [], int|string|null $key = null): Model;

    /**
     * @param  array<string, mixed>  $mutation
     * @param  array<string, mixed>  $attributes
     * @return list<Model>
     */
    public function mutations(array $mutation = [], array $attributes = []): array;
}
