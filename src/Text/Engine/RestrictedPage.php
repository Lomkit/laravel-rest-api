<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Query\ExtensionRestrictions;
use Lomkit\Rest\Text\Engine\Profiles\AppliesRowRestrictions;
use Lomkit\Rest\Text\Engine\Profiles\ComputesTotals;
use Lomkit\Rest\Text\Engine\Totals\EngineTotals;

/** @internal */
final readonly class RestrictedPage
{
    public function __construct(
        private AppliesRowRestrictions $profile,
        private ExtensionRestrictions $rows,
        private string $resource,
    ) {}

    public static function onto(EngineIndex $index, ExtensionRestrictions $rows, ?self $current): ?self
    {
        if ($current !== null) {
            throw InvalidEngineQueryException::repeatedRowRestriction($index->searchedClass(), $current->extensions());
        }

        return $index->profile instanceof AppliesRowRestrictions ? new self($index->profile, $rows, $index->searchedClass()) : null;
    }

    /** @param Builder<Model> $builder */
    public function read(Builder $builder, ScoutModel $model, int $perPage, int $page, ?EngineTotals $totals = null): EnginePage
    {
        $tracked = new TrackedRows($this->rows);
        $result = $totals !== null && $this->profile instanceof ComputesTotals
            ? $this->profile->totalsPage($builder, $model, $perPage, $page, $totals, $tracked)
            : $this->profile->restrictedPage($builder, $model, $perPage, $page, $tracked);

        if (! $tracked->applied() && ($result->keys !== [] || $result->total !== 0)) {
            throw InvalidEngineQueryException::unappliedRowRestriction($this->resource, $this->profile::class, $this->profile->name());
        }

        return $result;
    }

    /** @return non-empty-list<array{string, string}> */
    public function extensions(): array
    {
        return $this->rows->extensions();
    }
}
