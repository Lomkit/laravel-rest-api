<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\DatabaseEngine;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextTarget;

final readonly class DatabaseProfile implements EngineProfile
{
    public function __construct(private DatabaseEngine $engine) {}

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
        return [];
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
        $paginator = $this->engine->paginate($builder, $perPage, $page);
        $keys = array_map(fn (mixed $row): int|string => $row instanceof Model ? $model->keyOf($row) : $model->key($row), $paginator->items());

        return new EnginePage(array_values($keys), $paginator->total());
    }

    #[\Override]
    public function reachable(ScoutModel $model): int
    {
        return PHP_INT_MAX;
    }
}
