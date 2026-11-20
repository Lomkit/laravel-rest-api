<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Search\Totals\MeasureType;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\RowRestriction;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\Engine\Totals\EngineTotals;
use Lomkit\Rest\Text\TextTarget;

/** @internal */
interface ComputesTotals extends EngineProfile
{
    /** @return list<MeasureType> */
    public function measureTypes(Field $attribute): array;

    /** @return list<string> */
    public function totalsOptions(): array;

    public function ensureTotalsUsableBy(TextTarget $target): void;

    /** @param Builder<Model> $builder */
    public function totalsPage(Builder $builder, ScoutModel $model, int $perPage, int $page, ?EngineTotals $totals, ?RowRestriction $rows = null): EnginePage;
}
