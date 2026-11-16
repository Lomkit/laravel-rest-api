<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Text\Engine\Groups\FilterTree;

interface FiltersWithGroups extends EngineProfile
{
    /** @param Builder<Model> $builder */
    public function filterGroups(Builder $builder, FilterTree $tree): void;
}
