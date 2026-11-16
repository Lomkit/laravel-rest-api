<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;
use Lomkit\Rest\Text\TextQuery;

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
    public function build(IndexConditions $conditions, array $orders, array $options): Builder
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

        foreach ($orders === [] ? $this->stableOrder() : $orders as [$attribute, $direction]) {
            $builder->orderBy($attribute, $direction->value);
        }

        if ($builder::class !== Builder::class || $builder->callback !== null || $builder->queryCallback !== null) {
            throw InvalidTextSearchException::unexpectedBuilder($builder::class);
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

    /** @return list<array{string, SortDirection}> */
    private function stableOrder(): array
    {
        $key = $this->index->model->keyName();

        if ($this->text !== null || $this->lists()->listSortKeys() < 1 || ! $this->index->attributes->sorts($key)) {
            return [];
        }

        return [[$key, SortDirection::Asc]];
    }

    private function lists(): ListsWithoutText
    {
        $profile = $this->index->profile;

        return $profile instanceof ListsWithoutText ? $profile : throw InvalidTextSearchException::unlistedEngine($this->index->searchedClass(), $profile->name());
    }
}
