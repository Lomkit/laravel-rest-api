<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\Engine\Groups\FilterTree;
use Lomkit\Rest\Text\Engine\Profiles\FiltersWithGroups;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;
use Lomkit\Rest\Text\TextQuery;

/** @internal */
final readonly class EngineBuilder
{
    public function __construct(
        private EngineIndex $index,
        private ?TextQuery $text,
        private ?Trashed $trashed,
    ) {}

    /**
     * @param  list<array{string, SortDirection}>  $orders
     * @param  array<string, mixed>  $options
     * @return Builder<Model>
     */
    public function build(IndexConditions $conditions, array $orders, array $options, ?FilterTree $tree = null): Builder
    {
        $builder = new Builder($this->index->model->model(), $this->query(), null, $this->index->model->softDeletes());

        if ($options !== []) {
            $builder->options($options);
        }

        $conditions->applyTo($builder, $this->index->profile);

        match ($this->trashed) {
            Trashed::With => $builder->withTrashed(),
            Trashed::Only => $builder->onlyTrashed(),
            null => $builder,
        };

        foreach ($this->orders($orders) as [$attribute, $direction]) {
            $builder->orderBy($attribute, $direction->value);
        }

        if ($builder::class !== Builder::class || $builder->callback !== null || $builder->queryCallback !== null) {
            throw InvalidTextSearchException::unexpectedBuilder($builder::class);
        }

        if ($tree !== null) {
            $this->groups()->filterGroups($builder, $tree);
        }

        return $builder;
    }

    private function query(): string
    {
        if ($this->text !== null) {
            return implode(' ', $this->text->terms());
        }

        return $this->lists()->matchAll();
    }

    /**
     * @param  list<array{string, SortDirection}>  $orders
     * @return list<array{string, SortDirection}>
     */
    private function orders(array $orders): array
    {
        return $this->text === null ? $this->index->listOrder()->orders($orders, $this->index->model->keyName(), $this->index->attributes) : $orders;
    }

    private function groups(): FiltersWithGroups
    {
        $profile = $this->index->profile;

        return $profile instanceof FiltersWithGroups ? $profile : throw InvalidEngineQueryException::ungroupedFilters($this->index->searchedClass(), $profile->name());
    }

    private function lists(): ListsWithoutText
    {
        $profile = $this->index->profile;

        return $profile instanceof ListsWithoutText ? $profile : throw InvalidTextSearchException::unlistedEngine($this->index->searchedClass(), $profile->name());
    }
}
