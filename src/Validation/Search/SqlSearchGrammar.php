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
use Lomkit\Rest\Search\AggregateNode;
use Lomkit\Rest\Search\AggregateSort;
use Lomkit\Rest\Search\FieldSort;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Search\RelationSort;
use Lomkit\Rest\Search\RelevanceSort;
use Lomkit\Rest\Search\SortNode;
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
    public function relationFilters(Cursor $filters, ValidationContext $context): int
    {
        return (new RelationFilterCount($context->limits->depths->filters))->of($filters->value);
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
        return $limits->sizes->sorts;
    }

    #[\Override]
    public function sortFields(ValidationContext $context, int $count): SortPaths
    {
        $fields = $this->resourceSorts($context);

        if ($context->aggregates === []) {
            return new SortPaths($context->resource, $fields);
        }

        return new SortPaths($context->resource, new OneOf([...$fields->values(), ...array_map(static fn (AggregateNode $aggregate): string => $aggregate->key->value, $context->aggregates)]));
    }

    #[\Override]
    public function sortDirection(mixed $field): Enum
    {
        return Rule::enum(SortDirection::class);
    }

    #[\Override]
    public function sort(string $name, ?SortDirection $direction, ValidationContext $context): SortNode
    {
        if ($name === RelevanceClause::FIELD) {
            return new RelevanceSort($direction);
        }

        foreach ($context->aggregates as $aggregate) {
            if ($aggregate->key->value === $name) {
                return new AggregateSort($aggregate->key, $direction);
            }
        }

        $resource = $context->resource;

        if (str_contains($name, '.')) {
            return RelationSort::resolve($resource, $name, $direction);
        }

        $fields = $resource->definition()->fields();

        return new FieldSort($fields->get($name), $direction);
    }

    private function resourceSorts(ValidationContext $context): OneOf
    {
        $resource = $context->resource;
        $names = fn (): array => $resource
            ->definition()
            ->fields()
            ->sortable()
            ->names();

        if (! $this->ranks) {
            return $context->vocabulary->of($resource, $this, 'sorts', $names);
        }

        return $context->vocabulary->of($resource, $this, 'sorts+relevance', fn (): array => [...$names(), RelevanceClause::FIELD]);
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
    public function maxPage(mixed $limit, int $defaultLimit): ?int
    {
        return null;
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
