<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\RowRestriction;
use Lomkit\Rest\Text\Engine\ScoutModel;

interface AppliesRowRestrictions extends EngineProfile
{
    /** @param Builder<Model> $builder */
    public function restrictedPage(Builder $builder, ScoutModel $model, int $perPage, int $page, RowRestriction $rows): EnginePage;
}
