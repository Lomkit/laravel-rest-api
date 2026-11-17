<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\Profiles\EngineOperators;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;
use Lomkit\Rest\Text\Engine\Profiles\PlainLiterals;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextTarget;

final readonly class SingleSortProfile implements ListsWithoutText
{
    use PlainLiterals;

    #[\Override]
    public function name(): string
    {
        return ForeignEngine::class;
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

    /** @param Builder<Model> $builder */
    #[\Override]
    public function page(Builder $builder, ScoutModel $model, int $perPage, int $page): EnginePage
    {
        return EnginePage::empty();
    }

    #[\Override]
    public function reachable(ScoutModel $model): ?int
    {
        return null;
    }

    #[\Override]
    public function matchAll(): string
    {
        return '';
    }

    #[\Override]
    public function listSortKeys(): int
    {
        return 1;
    }
}
