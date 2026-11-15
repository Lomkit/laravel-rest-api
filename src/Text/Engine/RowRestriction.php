<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface RowRestriction
{
    /** @param Builder<Model> $query */
    public function apply(Builder $query): void;
}
