<?php

namespace Lomkit\Rest\Contracts;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Mutations\OperationEntry;

interface EloquentQueryBuilder extends QueryBuilder
{
    /** @return EloquentBuilder<Model> */
    #[\Override]
    public function toBase();

    /**
     * @param  array<string, mixed>  $parameters
     * @return list<OperationEntry>
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
