<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\TurbopufferEngine;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextTarget;

final readonly class TurbopufferProfile implements EngineProfile
{
    public function __construct(private TurbopufferEngine $engine) {}

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
        return 0;
    }

    #[\Override]
    public function ownedOptions(): array
    {
        return ['rank_by', 'filters', 'top_k', 'include_attributes'];
    }

    #[\Override]
    public function exactTotal(): bool
    {
        return false;
    }

    #[\Override]
    public function maxPageSize(): ?int
    {
        return null;
    }

    #[\Override]
    public function window(): int
    {
        return 10000;
    }

    #[\Override]
    public function ensureUsableBy(TextTarget $target): void {}

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
}
