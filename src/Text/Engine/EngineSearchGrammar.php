<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Instructions\InstructionSupport;
use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Text\TextBackend;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\Limits;
use Lomkit\Rest\Validation\Search\SearchGrammar;
use Lomkit\Rest\Validation\Search\SqlSearchGrammar;
use Lomkit\Rest\Validation\Search\TextParser;
use Lomkit\Rest\Validation\ValidationContext;

/** @internal */
final readonly class EngineSearchGrammar implements SearchGrammar
{
    public function __construct(
        private EngineGrammar $engine,
        private TextCapabilities $text,
    ) {}

    #[\Override]
    public function backend(): TextBackend
    {
        return TextBackend::Engine;
    }

    #[\Override]
    public function filters(array $entries, ValidationContext $context): FilterGroup
    {
        return (new EngineFilterParser($this->engine))->group($entries, $context);
    }

    #[\Override]
    public function scopeRules(): array
    {
        return ['prohibited'];
    }

    #[\Override]
    public function scopes(array $entries, ValidationContext $context): array
    {
        return [];
    }

    #[\Override]
    public function sortLimit(Limits $limits): int
    {
        if ($this->engine->sortKeys === PHP_INT_MAX) {
            return $limits->sizes->sorts;
        }

        return min($limits->sizes->sorts, max(1, $this->engine->sortKeys));
    }

    #[\Override]
    public function sortFields(ValidationContext $context, int $count): OneOf
    {
        if ($count !== 1) {
            return $context->vocabulary->of($context->resource, $this, 'sorts', fn (): array => $this->engine->sorts);
        }

        return $context->vocabulary->of($context->resource, $this, 'sorts+relevance', fn (): array => [...$this->engine->sorts, RelevanceClause::FIELD]);
    }

    #[\Override]
    public function sortDirection(mixed $field): Enum
    {
        return Rule::enum(SortDirection::class)->only($field === RelevanceClause::FIELD ? [SortDirection::Desc] : SortDirection::cases());
    }

    #[\Override]
    public function instructions(array $declared): array
    {
        return InstructionSupport::among($declared, TextBackend::Engine);
    }

    #[\Override]
    public function limitRules(): array
    {
        return $this->engine->maxPageSize === null ? [] : ['max:'.$this->engine->maxPageSize];
    }

    #[\Override]
    public function pageRules(mixed $limit, int $defaultLimit): array
    {
        if ($this->engine->window === null) {
            return [];
        }

        $perPage = filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return ['max:'.intdiv($this->engine->window, $perPage === false ? $defaultLimit : $perPage)];
    }

    #[\Override]
    public function text(Cursor $text, ValidationContext $context): ?TextQuery
    {
        return (new TextParser($this->text))->parse($text, $context);
    }

    #[\Override]
    public function trashed(Resource $resource): bool
    {
        return $this->text->trashed;
    }

    #[\Override]
    public function nested(): SearchGrammar
    {
        return new SqlSearchGrammar(null, false);
    }
}
