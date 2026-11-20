<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Validation\ValidationContext;

/** @internal */
final readonly class SqlSortFields
{
    public static function of(ValidationContext $context, SearchGrammar $grammar, bool $ranks): OneOf
    {
        $resource = $context->resource;
        $names = fn (): array => $resource
            ->definition()
            ->fields()
            ->sortable()
            ->names();

        if (! $ranks) {
            return $context->vocabulary->of($resource, $grammar, 'sorts', $names);
        }

        return $context->vocabulary->of($resource, $grammar, 'sorts+relevance', fn (): array => [...$names(), RelevanceClause::FIELD]);
    }
}
