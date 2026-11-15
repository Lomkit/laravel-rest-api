<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Query\ExtensionRestrictions;
use Lomkit\Rest\Text\Engine\Profiles\AppliesRowRestrictions;

/** @internal */
final readonly class RestrictedPage
{
    public function __construct(
        private AppliesRowRestrictions $profile,
        private ExtensionRestrictions $rows,
        private string $resource,
    ) {}

    /** @param Builder<Model> $builder */
    public function read(Builder $builder, ScoutModel $model, int $perPage, int $page): EnginePage
    {
        $tracked = new TrackedRows($this->rows);
        $result = $this->profile->restrictedPage($builder, $model, $perPage, $page, $tracked);

        if (! $tracked->applied() && ($result->keys !== [] || $result->total !== 0)) {
            throw InvalidEngineQueryException::unappliedRowRestriction($this->resource, $this->profile::class, $this->profile->name());
        }

        return $result;
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->rows->names();
    }
}
