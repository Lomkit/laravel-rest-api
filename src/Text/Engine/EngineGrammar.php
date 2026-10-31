<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Fields\Filtering\FilterOperator;

final readonly class EngineGrammar
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
}
