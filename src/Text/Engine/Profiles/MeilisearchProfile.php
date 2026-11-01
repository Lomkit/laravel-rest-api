<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\MeilisearchEngine;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextTarget;
use Throwable;

final readonly class MeilisearchProfile implements EngineProfile
{
    public function __construct(private MeilisearchEngine $engine) {}

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
        return PHP_INT_MAX;
    }

    #[\Override]
    public function ownedOptions(): array
    {
        return ['filter', 'sort', 'page', 'hitsPerPage', 'limit', 'offset', 'q', 'attributesToRetrieve'];
    }

    #[\Override]
    public function exactTotal(): bool
    {
        return true;
    }

    #[\Override]
    public function maxPageSize(): ?int
    {
        return null;
    }

    #[\Override]
    public function window(): ?int
    {
        return null;
    }

    #[\Override]
    public function ensureUsableBy(TextTarget $target): void {}

    #[\Override]
    public function page(Builder $builder, ScoutModel $model, int $perPage, int $page): EnginePage
    {
        return EngineHits::page($this->engine, $builder, $model, $perPage, $page);
    }

    #[\Override]
    public function reachable(ScoutModel $model): ?int
    {
        try {
            $index = $this->engine->__call('index', [$model->indexName()]);
            $pagination = is_object($index) && method_exists($index, 'getPagination') ? $index->getPagination() : null;
        } catch (Throwable) {
            return null;
        }

        $maximum = is_array($pagination) ? ($pagination['maxTotalHits'] ?? null) : null;

        return is_int($maximum) && $maximum >= 0 ? $maximum : null;
    }
}
