<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Text\Engine\EngineGrammar;

final class EngineSort extends RestRule
{
    public function __construct(
        private readonly EngineGrammar $grammar,
        private readonly bool $alone,
    ) {}

    /** @return array<string, list<mixed>> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $field = is_array($value) ? ($value['field'] ?? null) : null;
        $directions = $field === RelevanceClause::FIELD ? [SortDirection::Desc] : SortDirection::cases();

        return [
            $attribute.'.field' => [
                new OneOf($this->alone ? [...$this->grammar->sorts, RelevanceClause::FIELD] : $this->grammar->sorts),
                'required',
                'string',
            ],
            $attribute.'.direction' => ['sometimes', Rule::enum(SortDirection::class)->only($directions)],
        ];
    }
}
