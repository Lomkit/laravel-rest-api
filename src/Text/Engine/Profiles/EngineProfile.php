<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextTarget;

interface EngineProfile
{
    public function name(): string;

    /** @return list<FilterOperator> */
    public function operators(Field $attribute): array;

    public function sortKeys(): int;

    /** @return list<string> */
    public function ownedOptions(): array;

    public function exactTotal(): bool;

    public function maxPageSize(): ?int;

    public function window(): ?int;

    public function ensureUsableBy(TextTarget $target): void;

    /** @param Builder<Model> $builder */
    public function page(Builder $builder, ScoutModel $model, int $perPage, int $page): EnginePage;

    public function reachable(ScoutModel $model): ?int;
}
