<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Laravel\Scout\Builder;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\EngineLiteral;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\Profiles\DatabaseProfile;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\RowRestriction;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextTarget;

final readonly class NoSqlDatabaseProfile implements EngineProfile
{
    public function __construct(private DatabaseProfile $profile) {}

    #[\Override]
    public function name(): string
    {
        return $this->profile->name();
    }

    #[\Override]
    public function operators(Field $attribute): array
    {
        return $this->profile->operators($attribute);
    }

    #[\Override]
    public function sortKeys(): int
    {
        return $this->profile->sortKeys();
    }

    #[\Override]
    public function ownedOptions(): array
    {
        return $this->profile->ownedOptions();
    }

    #[\Override]
    public function exactTotal(): bool
    {
        return $this->profile->exactTotal();
    }

    #[\Override]
    public function maxPageSize(): ?int
    {
        return $this->profile->maxPageSize();
    }

    #[\Override]
    public function window(): ?int
    {
        return $this->profile->window();
    }

    #[\Override]
    public function ensureUsableBy(TextTarget $target): void
    {
        $this->profile->ensureUsableBy($target);
    }

    #[\Override]
    public function page(Builder $builder, ScoutModel $model, int $perPage, int $page, ?RowRestriction $rows = null): EnginePage
    {
        return $this->profile->page($builder, $model, $perPage, $page, $rows);
    }

    #[\Override]
    public function reachable(ScoutModel $model): ?int
    {
        return $this->profile->reachable($model);
    }

    #[\Override]
    public function literal(): EngineLiteral
    {
        return $this->profile->literal();
    }

    #[\Override]
    public function matchesStringLists(): bool
    {
        return false;
    }

    #[\Override]
    public function appliesRowRestrictions(): bool
    {
        return false;
    }
}
