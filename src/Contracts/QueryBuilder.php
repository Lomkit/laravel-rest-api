<?php

namespace Lomkit\Rest\Contracts;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder as ScoutBuilder;

interface QueryBuilder
{
    /**
     * @param  array<string, mixed>  $parameters
     * @return EloquentBuilder<Model>|ScoutBuilder<Model>
     */
    public function search(array $parameters = []);

    /** @return EloquentBuilder<Model>|ScoutBuilder<Model> */
    public function toBase();
}
