<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\Profiles\EngineOperators;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\PlainLiterals;
use Lomkit\Rest\Text\Engine\RowRestriction;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextTarget;

final class ForeignProfile implements EngineProfile
{
    use PlainLiterals;

    /** @var list<array{int, int, ?RowRestriction}> */
    public static array $pages = [];

    /** @var list<string> */
    public static array $sql = [];

    /** @var list<Builder<Model>> */
    public static array $builders = [];

    public function __construct(
        private readonly object $engine,
        private readonly int $sortKeys = PHP_INT_MAX,
    ) {}

    public static function reset(): void
    {
        self::$pages = [];
        self::$builders = [];
        self::$sql = [];
    }

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
        return $this->sortKeys;
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
        self::$pages[] = [$perPage, $page, null];
        self::$builders[] = $builder;

        return new EnginePage(['V-a'], 1);
    }

    #[\Override]
    public function reachable(ScoutModel $model): int
    {
        return PHP_INT_MAX;
    }
}
