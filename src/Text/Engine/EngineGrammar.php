<?php

namespace Lomkit\Rest\Text\Engine;

use JsonSerializable;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
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

    /** @internal */
    public function searchGrammar(TextCapabilities $text): SearchGrammar
    {
        return new EngineSearchGrammar($this, $text);
    }

    /** @return array{filters: list<IndexAttribute>, sorts: list<string>, max_sorts: int|null, max_page_size: int|null, window: int|null} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'filters' => array_values($this->filters),
            'sorts' => $this->sorts,
            'max_sorts' => $this->sortKeys === PHP_INT_MAX ? null : $this->sortKeys,
            'max_page_size' => $this->maxPageSize,
            'window' => $this->window,
        ];
    }
}
