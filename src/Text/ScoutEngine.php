<?php

namespace Lomkit\Rest\Text;

use Closure;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\Engine\EngineIndex;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\IndexAttributes;
use Lomkit\Rest\Text\Engine\PerimeterDeclaration;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfiles;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\Engine\ServerAttributeNames;
use Lomkit\Rest\Text\Engine\TieBreaker;

final readonly class ScoutEngine implements ListingTextSearch
{
    /**
     * @param  array<mixed>  $filterable
     * @param  array<mixed>  $sortable
     * @param  (Closure(EngineQuery): mixed)|null  $perimeter
     * @param  array<mixed>  $perimeterAttributes
     * @param  array<mixed>  $indexAttributes
     * @param  array<mixed>  $groupable
     * @param  array<mixed>  $measurable
     */
    private function __construct(
        private array $filterable = [],
        private array $sortable = [],
        private ?Closure $perimeter = null,
        private array $perimeterAttributes = [],
        private array $indexAttributes = [],
        private bool $withoutPerimeter = false,
        private bool $lists = false,
        private ?string $tieBreaker = null,
        private array $groupable = [],
        private array $measurable = [],
    ) {}

    public static function make(): self
    {
        return new self;
    }

    /** @param array<mixed> $attributes */
    public function filterable(array $attributes): self
    {
        return $this->with(filterable: $attributes);
    }

    /** @param array<mixed> $attributes */
    public function sortable(array $attributes): self
    {
        return $this->with(sortable: $attributes);
    }

    /**
     * @param  Closure(EngineQuery): mixed  $perimeter
     * @param  array<mixed>  $attributes  Deprecated, to be removed in 4.0: declare these attributes with indexAttributes().
     */
    public function perimeter(Closure $perimeter, array $attributes = []): self
    {
        return $this->with(perimeter: $perimeter, perimeterAttributes: $attributes);
    }

    /** @param array<mixed> $attributes */
    public function indexAttributes(array $attributes): self
    {
        return $this->with(indexAttributes: $attributes);
    }

    public function withoutPerimeter(): self
    {
        return $this->with(withoutPerimeter: true);
    }

    public function servesLists(bool $serves = true): static
    {
        return $this->with(lists: $serves);
    }

    public function tieBreaker(string $attribute): self
    {
        return $this->with(tieBreaker: $attribute);
    }

    /** @param array<mixed> $attributes */
    public function groupable(array $attributes): self
    {
        return $this->with(groupable: $attributes);
    }

    /** @param array<mixed> $attributes */
    public function measurable(array $attributes): self
    {
        return $this->with(measurable: $attributes);
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

    /**
     * @internal
     *
     * @return list<string>
     */
    public function serverNames(): array
    {
        return ServerAttributeNames::of([...array_values($this->perimeterAttributes), ...array_values($this->indexAttributes)]);
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

    /**
     * @param  array<mixed>|null  $filterable
     * @param  array<mixed>|null  $sortable
     * @param  (Closure(EngineQuery): mixed)|null  $perimeter
     * @param  array<mixed>|null  $perimeterAttributes
     * @param  array<mixed>|null  $indexAttributes
     * @param  array<mixed>|null  $groupable
     * @param  array<mixed>|null  $measurable
     */
    private function with(?array $filterable = null, ?array $sortable = null, ?Closure $perimeter = null, ?array $perimeterAttributes = null, ?array $indexAttributes = null, ?bool $withoutPerimeter = null, ?bool $lists = null, ?string $tieBreaker = null, ?array $groupable = null, ?array $measurable = null): self
    {
        return new self(
            filterable: $filterable ?? $this->filterable,
            sortable: $sortable ?? $this->sortable,
            perimeter: $perimeter ?? $this->perimeter,
            perimeterAttributes: $perimeterAttributes ?? $this->perimeterAttributes,
            indexAttributes: $indexAttributes ?? $this->indexAttributes,
            withoutPerimeter: $withoutPerimeter ?? $this->withoutPerimeter,
            lists: $lists ?? $this->lists,
            tieBreaker: $tieBreaker ?? $this->tieBreaker,
            groupable: $groupable ?? $this->groupable,
            measurable: $measurable ?? $this->measurable,
        );
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
        PerimeterDeclaration::ensure($this->perimeter, $this->withoutPerimeter, $target->resource);

        $model = ScoutModel::tryFrom($target->model) ?? throw InvalidTextSearchException::unsearchableModel($target->resource, $target->model::class);
        $profile = EngineProfiles::forTarget($target);
        $profile->ensureUsableBy($target);

        if ($this->lists && ! $profile instanceof ListsWithoutText) {
            throw InvalidTextSearchException::unlistedEngine($target->resource, $profile->name());
        }

        $tieBreaker = $this->tieBreaker === null ? null : TieBreaker::declared($this->tieBreaker, $target->resource, $profile, $this->lists);
        $attributes = IndexAttributes::of($target, $profile, $this->filterable, $this->sortable, $this->perimeterAttributes, $this->lists, $this->indexAttributes, [$this->groupable, $this->measurable]);

        return new EngineIndex($model, $profile, $attributes, $target->resource, $this->lists, $tieBreaker);
    }
}
