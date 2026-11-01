<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Instructions\InstructionSupport;
use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Text\TextBackend;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\Limits;
use Lomkit\Rest\Validation\ValidationContext;

/** @internal */
final readonly class SqlSearchGrammar implements SearchGrammar
{
    public function __construct(
        private ?TextCapabilities $text,
        private bool $ranks,
    ) {}

    #[\Override]
    public function backend(): TextBackend
    {
        return TextBackend::Query;
    }

    #[\Override]
    public function filters(array $entries, ValidationContext $context): FilterGroup
    {
        return (new FilterParser)->group($entries, $context);
    }

    #[\Override]
    public function scopeRules(): array
    {
        return [];
    }

    #[\Override]
    public function scopes(array $entries, ValidationContext $context): array
    {
        return (new ScopeParser)->parse($entries, $context);
    }

    #[\Override]
    public function sortLimit(Limits $limits): int
    {
        return $limits->sorts;
    }

    #[\Override]
    public function sortFields(ValidationContext $context, int $count): OneOf
    {
        $resource = $context->resource;
        $names = fn (): array => $resource
            ->getFields($resource->context()->request)
            ->sortable()
            ->names();

        if (! $this->ranks) {
            return $context->vocabulary->of($resource, $this, 'sorts', $names);
        }

        return $context->vocabulary->of($resource, $this, 'sorts+relevance', fn (): array => [...$names(), RelevanceClause::FIELD]);
    }

    #[\Override]
    public function sortDirection(mixed $field): Enum
    {
        return Rule::enum(SortDirection::class);
    }

    #[\Override]
    public function instructions(array $declared): array
    {
        return InstructionSupport::among($declared, TextBackend::Query);
    }

    #[\Override]
    public function limitRules(): array
    {
        return [];
    }

    #[\Override]
    public function pageRules(mixed $limit, int $defaultLimit): array
    {
        return [];
    }

    #[\Override]
    public function text(Cursor $text, ValidationContext $context): ?TextQuery
    {
        if ($this->text === null) {
            $context->rules->add($text->path, ['prohibited']);

            return null;
        }

        return (new TextParser($this->text))->parse($text, $context);
    }

    #[\Override]
    public function trashed(Resource $resource): bool
    {
        return Trashed::supportedBy($resource::modelClass());
    }

    #[\Override]
    public function nested(): SearchGrammar
    {
        return new self(null, false);
    }
}
