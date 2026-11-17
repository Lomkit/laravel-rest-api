<?php

namespace Lomkit\Rest\Text;

use Closure;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\Engine\EngineIndex;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\IndexAttributes;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfiles;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\Engine\TieBreaker;

final readonly class ScoutEngine implements ListingTextSearch
{
    /**
     * @param  array<mixed>  $filterable
     * @param  array<mixed>  $sortable
     * @param  (Closure(EngineQuery): mixed)|null  $perimeter
     * @param  array<mixed>  $perimeterAttributes
     */
    private function __construct(
        private array $filterable = [],
        private array $sortable = [],
        private ?Closure $perimeter = null,
        private array $perimeterAttributes = [],
        private bool $withoutPerimeter = false,
        private bool $lists = false,
        private ?string $tieBreaker = null,
        private bool $databaseLists = false,
    ) {}

    public static function make(): self
    {
        return new self;
    }

    /** @param array<mixed> $attributes */
    public function filterable(array $attributes): self
    {
        return new self($attributes, $this->sortable, $this->perimeter, $this->perimeterAttributes, $this->withoutPerimeter, $this->lists, $this->tieBreaker, $this->databaseLists);
    }

    /** @param array<mixed> $attributes */
    public function sortable(array $attributes): self
    {
        return new self($this->filterable, $attributes, $this->perimeter, $this->perimeterAttributes, $this->withoutPerimeter, $this->lists, $this->tieBreaker, $this->databaseLists);
    }

    /**
     * @param  Closure(EngineQuery): mixed  $perimeter
     * @param  array<mixed>  $attributes
     */
    public function perimeter(Closure $perimeter, array $attributes = []): self
    {
        return new self($this->filterable, $this->sortable, $perimeter, $attributes, $this->withoutPerimeter, $this->lists, $this->tieBreaker, $this->databaseLists);
    }

    public function withoutPerimeter(): self
    {
        return new self($this->filterable, $this->sortable, $this->perimeter, $this->perimeterAttributes, true, $this->lists, $this->tieBreaker, $this->databaseLists);
    }

    public function servesLists(bool $serves = true): static
    {
        return new self($this->filterable, $this->sortable, $this->perimeter, $this->perimeterAttributes, $this->withoutPerimeter, $serves, $this->tieBreaker, $this->databaseLists);
    }

    public function tieBreaker(string $attribute): self
    {
        return new self($this->filterable, $this->sortable, $this->perimeter, $this->perimeterAttributes, $this->withoutPerimeter, $this->lists, $attribute, $this->databaseLists);
    }

    public function allowsDatabaseLists(bool $allows = true): static
    {
        return new self($this->filterable, $this->sortable, $this->perimeter, $this->perimeterAttributes, $this->withoutPerimeter, $this->lists, $this->tieBreaker, $allows);
    }

    /** @internal */
    #[\Override]
    public function servingLists(): bool
    {
        return $this->lists;
    }

    /** @internal */
    #[\Override]
    public function listTieBreaker(): ?string
    {
        return $this->tieBreaker;
    }

    /** @internal */
    #[\Override]
    public function databaseListsAllowed(): bool
    {
        return $this->lists && $this->databaseLists;
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    public function perimeterNames(): array
    {
        $names = array_map(static fn (mixed $attribute): ?string => match (true) {
            $attribute instanceof Field => $attribute->name,
            is_string($attribute) => $attribute,
            default => null,
        }, $this->perimeterAttributes);

        return array_values(array_filter($names, is_string(...)));
    }

    #[\Override]
    public function capabilities(TextTarget $target): TextCapabilities
    {
        return $this->index($target)->capabilities();
    }

    #[\Override]
    public function query(TextQuery $text, TextTarget $target, ?Trashed $trashed = null): EngineQuery
    {
        return $this->bounded(new EngineQuery($this->index($target), $text, $trashed));
    }

    /** @internal */
    #[\Override]
    public function list(TextTarget $target, ?Trashed $trashed = null): EngineQuery
    {
        if (! $this->lists) {
            throw InvalidTextSearchException::notOnEngine($target->resource);
        }

        return $this->bounded(new EngineQuery($this->index($target), null, $trashed));
    }

    private function bounded(EngineQuery $query): EngineQuery
    {
        if ($this->perimeter !== null) {
            ($this->perimeter)($query);
        }

        return $query;
    }

    private function index(TextTarget $target): EngineIndex
    {
        if ($this->perimeter === null && ! $this->withoutPerimeter) {
            throw InvalidTextSearchException::missingPerimeter($target->resource);
        }

        if ($this->perimeter !== null && $this->withoutPerimeter) {
            throw InvalidTextSearchException::conflictingPerimeter($target->resource);
        }

        $model = ScoutModel::tryFrom($target->model) ?? throw InvalidTextSearchException::unsearchableModel($target->resource, $target->model::class);
        $profile = EngineProfiles::forTarget($target);

        $profile->ensureUsableBy($target);

        if ($this->lists && ! $profile instanceof ListsWithoutText) {
            throw InvalidTextSearchException::unlistedEngine($target->resource, $profile->name());
        }

        if ($this->databaseLists && ! $this->lists) {
            throw InvalidTextSearchException::unlistedDatabaseLists($target->resource);
        }

        $tieBreaker = $this->tieBreaker === null ? null : TieBreaker::declared($this->tieBreaker, $target->resource, $profile, $this->lists);
        $attributes = IndexAttributes::of($target, $profile, $this->filterable, $this->sortable, $this->perimeterAttributes, $this->lists);

        return new EngineIndex($model, $profile, $attributes, $target->resource, $this->lists, $tieBreaker, $this->databaseLists);
    }
}
