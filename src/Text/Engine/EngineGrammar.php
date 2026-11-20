<?php

namespace Lomkit\Rest\Text\Engine;

use JsonSerializable;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Text\Engine\Totals\TotalsGrammar;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Validation\Search\SearchGrammar;

final readonly class EngineGrammar implements JsonSerializable
{
    /**
     * @param  array<string, IndexAttribute>  $filters
     * @param  list<string>  $sorts
     */
    public function __construct(
        public array $filters,
        public array $sorts,
        public int $sortKeys = PHP_INT_MAX,
        public ?int $maxPageSize = null,
        public ?int $window = null,
        public ?int $listSortKeys = null,
        public bool $groups = false,
        public ?string $tieBreaker = null,
        public ?TotalsGrammar $totals = null,
    ) {}

    /** @return list<FilterOperator> */
    public function operators(string $attribute): array
    {
        return $this->filters[$attribute]->operators ?? [];
    }

    public function sorts(string $attribute): bool
    {
        return in_array($attribute, $this->sorts, true);
    }

    /**
     * @internal
     *
     * @param  list<string>  $unfilterable
     * @param  list<string>  $unsortable
     */
    public function without(array $unfilterable, array $unsortable): self
    {
        if ($unfilterable === [] && $unsortable === []) {
            return $this;
        }

        return new self(
            array_diff_key($this->filters, array_flip($unfilterable)),
            array_values(array_diff($this->sorts, $unsortable)),
            $this->sortKeys,
            $this->maxPageSize,
            $this->window,
            $this->listSortKeys,
            $this->groups,
            $this->tieBreaker,
            $this->totals?->without($unfilterable),
        );
    }

    /** @internal */
    public function listing(int $sortKeys, ?string $tieBreaker = null): self
    {
        return new self($this->filters, $this->sorts, $this->sortKeys, $this->maxPageSize, $this->window, $sortKeys, $this->groups, $tieBreaker, $this->totals);
    }

    /** @internal */
    public function unlisted(): self
    {
        return new self($this->filters, $this->sorts, $this->sortKeys, $this->maxPageSize, $this->window, groups: $this->groups, totals: $this->totals);
    }

    /** @internal */
    public function servesLists(): bool
    {
        return $this->listSortKeys !== null;
    }

    /** @internal */
    public function searchGrammar(TextCapabilities $text, bool $listing = false): SearchGrammar
    {
        return new EngineSearchGrammar($this, $text, $listing && $this->servesLists());
    }

    /** @return array{filters: list<IndexAttribute>, sorts: list<string>, max_sorts: int|null, max_page_size: int|null, window: int|null, lists?: true, max_list_sorts?: int|null, tie_breaker?: string, groups?: true, totals?: TotalsGrammar} */
    #[\Override]
    public function jsonSerialize(): array
    {
        $lists = $this->listSortKeys === null ? [] : ['lists' => true, 'max_list_sorts' => self::bound($this->listSortKeys)];
        $tieBreaker = $this->tieBreaker === null ? [] : ['tie_breaker' => $this->tieBreaker];
        $groups = $this->groups ? ['groups' => true] : [];
        $totals = $this->totals?->declares() === true ? ['totals' => $this->totals] : [];

        return [
            'filters' => array_values($this->filters),
            'sorts' => $this->sorts,
            'max_sorts' => self::bound($this->sortKeys),
            'max_page_size' => $this->maxPageSize,
            'window' => $this->window,
            ...$lists,
            ...$tieBreaker,
            ...$groups,
            ...$totals,
        ];
    }

    private static function bound(int $keys): ?int
    {
        return $keys === PHP_INT_MAX ? null : $keys;
    }
}
