<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Laravel\Scout\Builder;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Text\Engine\EngineLiteral;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\Profiles\AppliesRowRestrictions;
use Lomkit\Rest\Text\Engine\RowRestriction;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextTarget;

final readonly class RestrictingForeignProfile implements AppliesRowRestrictions
{
    public function __construct(
        private ForeignProfile $profile,
        private bool $skipsRows = false,
    ) {}

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
    public function page(Builder $builder, ScoutModel $model, int $perPage, int $page): EnginePage
    {
        return $this->profile->page($builder, $model, $perPage, $page);
    }

    #[\Override]
    public function restrictedPage(Builder $builder, ScoutModel $model, int $perPage, int $page, RowRestriction $rows): EnginePage
    {
        ForeignProfile::$pages[] = [$perPage, $page, $rows];
        ForeignProfile::$builders[] = $builder;

        if (! $this->skipsRows) {
            $query = Voucher::query();
            $rows->apply($query);
            ForeignProfile::$sql[] = $query->toSql();
        }

        return new EnginePage(['V-a'], 1);
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
}
