<?php

namespace Lomkit\Rest\Contracts;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

interface QueryBuilder
{
    /**
     * @param  array<string, mixed>  $parameters
     * @return EloquentBuilder<Model>
     */
    public function search(array $parameters = []);

    /** @return EloquentBuilder<Model> */
    public function toBase();
}
