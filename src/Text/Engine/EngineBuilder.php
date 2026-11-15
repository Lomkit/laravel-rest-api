<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\TextQuery;

final readonly class EngineBuilder
{
    public function __construct(
        private EngineIndex $index,
        private TextQuery $text,
        private ?Trashed $trashed,
    ) {}

    /**
     * @param  list<array{string, SortDirection}>  $orders
     * @param  array<string, mixed>  $options
     * @return Builder<Model>
     */
    public function build(IndexConditions $conditions, array $orders, array $options): Builder
    {
        $builder = new Builder($this->index->model->model(), implode(' ', $this->text->terms()), null, $this->index->model->softDeletes());

        $conditions->applyTo($builder);

        match ($this->trashed) {
            Trashed::With => $builder->withTrashed(),
            Trashed::Only => $builder->onlyTrashed(),
            null => $builder,
        };

        foreach ($orders as [$attribute, $direction]) {
            $builder->orderBy($attribute, $direction->value);
        }

        if ($options !== []) {
            $builder->options($options);
        }

        if ($builder::class !== Builder::class || $builder->callback !== null || $builder->queryCallback !== null) {
            throw InvalidTextSearchException::unexpectedBuilder($builder::class);
        }

        return $builder;
    }
}
