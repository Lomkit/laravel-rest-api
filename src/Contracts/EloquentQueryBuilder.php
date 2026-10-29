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
    public function search(array $parameters = []);

    /** @return EloquentBuilder<Model> */
    public function toBase();

    /** @return $this */
    public function disableDefaultLimit(bool $disable = true);

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function mutate(array $parameters = []);

    /**
     * @param  array<string, mixed>  $mutation
     * @param  array<string, mixed>  $attributes
     * @return Model
     */
    public function applyMutation(array $mutation = [], array $attributes = [], mixed $key = null);

    /**
     * @param  array<string, mixed>  $mutation
     * @param  array<string, mixed>  $attributes
     * @return array<int, Model>
     */
    public function mutations(array $mutation = [], array $attributes = []);
}
