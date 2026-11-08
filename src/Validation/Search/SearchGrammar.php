<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Validation\Rules\Enum;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Rules\AllowedValues;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Search\ScopeNode;
use Lomkit\Rest\Search\SortNode;
use Lomkit\Rest\Text\TextBackend;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\Limits;
use Lomkit\Rest\Validation\ValidationContext;

/** @internal */
interface SearchGrammar
{
    public function backend(): TextBackend;

    /** @param list<Cursor> $entries */
    public function filters(array $entries, ValidationContext $context): FilterGroup;

    public function relationFilters(Cursor $filters, ValidationContext $context): int;

    /** @return list<string> */
    public function scopeRules(): array;

    /**
     * @param  list<Cursor>  $entries
     * @return list<ScopeNode>
     */
    public function scopes(array $entries, ValidationContext $context): array;

    public function sortLimit(Limits $limits): int;

    public function sortFields(ValidationContext $context, int $count): AllowedValues;

    public function sortDirection(mixed $field): Enum;

    public function sort(string $name, ?SortDirection $direction, ValidationContext $context): SortNode;

    /**
     * @param  array<int, Instruction>  $declared
     * @return array<int, Instruction>
     */
    public function instructions(array $declared): array;

    /** @return list<string> */
    public function limitRules(): array;

    /** @return list<string> */
    public function pageRules(mixed $limit, int $defaultLimit): array;

    public function text(Cursor $text, ValidationContext $context): ?TextQuery;

    public function trashed(Resource $resource): bool;

    public function nested(): SearchGrammar;
}
