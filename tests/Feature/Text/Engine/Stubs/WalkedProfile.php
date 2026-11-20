<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\EngineLiteral;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextTarget;

final readonly class WalkedProfile implements ListsWithoutText
{
    public function __construct(private ListsWithoutText $profile) {}

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
        return WalkedEngineProfiles::$exactTotal ?? $this->profile->exactTotal();
    }

    #[\Override]
    public function maxPageSize(): int
    {
        return WalkedEngineProfiles::$pageSize;
    }

    #[\Override]
    public function window(): ?int
    {
        return WalkedEngineProfiles::$window;
    }

    #[\Override]
    public function ensureUsableBy(TextTarget $target): void
    {
        $this->profile->ensureUsableBy($target);
    }

    /** @param Builder<Model> $builder */
    #[\Override]
    public function page(Builder $builder, ScoutModel $model, int $perPage, int $page): EnginePage
    {
        WalkedEngineProfiles::$pages[] = [$perPage, $page];

        return $this->profile->page($builder, $model, $perPage, $page);
    }

    #[\Override]
    public function reachable(ScoutModel $model): ?int
    {
        return WalkedEngineProfiles::$reachable;
    }

    #[\Override]
    public function literal(): EngineLiteral
    {
        return $this->profile->literal();
    }

    #[\Override]
    public function matchAll(): string
    {
        return $this->profile->matchAll();
    }

    #[\Override]
    public function listSortKeys(): int
    {
        return $this->profile->listSortKeys();
    }
}
