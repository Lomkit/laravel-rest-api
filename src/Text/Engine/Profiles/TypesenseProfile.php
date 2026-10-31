<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextTarget;

final readonly class TypesenseProfile implements EngineProfile
{
    public function __construct(private TypesenseEngine $engine) {}

    #[\Override]
    public function name(): string
    {
        return $this->engine::class;
    }

    #[\Override]
    public function operators(Field $attribute): array
    {
        return EngineOperators::for($attribute);
    }

    #[\Override]
    public function sortKeys(): int
    {
        return 3;
    }

    #[\Override]
    public function ownedOptions(): array
    {
        return ['q', 'filter_by', 'sort_by', 'per_page', 'page', 'group_by'];
    }

    #[\Override]
    public function exactTotal(): bool
    {
        return true;
    }

    #[\Override]
    public function maxPageSize(): int
    {
        return 250;
    }

    #[\Override]
    public function window(): ?int
    {
        return null;
    }

    #[\Override]
    public function ensureUsableBy(TextTarget $target): void
    {
        $queryBy = $this->queryBy($target->model);

        if (! is_string($queryBy) || trim($queryBy) === '') {
            throw InvalidTextSearchException::missingQueryBy($target->resource, $target->model::class);
        }
    }

    #[\Override]
    public function page(Builder $builder, ScoutModel $model, int $perPage, int $page): EnginePage
    {
        return EngineHits::page($this->engine, $builder, $model, $perPage, $page);
    }

    #[\Override]
    public function reachable(ScoutModel $model): int
    {
        return PHP_INT_MAX;
    }

    private function queryBy(Model $model): mixed
    {
        $parameters = method_exists($model, 'typesenseSearchParameters') ? $model->typesenseSearchParameters() : null;

        if (is_array($parameters) && isset($parameters['query_by'])) {
            return $parameters['query_by'];
        }

        return config('scout.typesense.model-settings.'.$model::class.'.search-parameters.query_by');
    }
}
