<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Text\ListingTextSearch;
use Lomkit\Rest\Text\ListSource;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;
use Lomkit\Rest\Validation\Violation;

/** @internal */
final readonly class SourceParser
{
    public function parse(Cursor $source, ?TextQuery $text, ValidationContext $context): void
    {
        if (! $source->present) {
            return;
        }

        $search = $context->resource->text();

        if (! $search instanceof ListingTextSearch || ! $search->databaseListsAllowed()) {
            $context->rules->reject(Violation::unknown($source->path));

            return;
        }

        $context->rules->add($source->path, [Rule::enum(ListSource::class)]);

        if ($source->value === ListSource::Database->value && $text?->hasTerms() === true) {
            $context->rules->reject(Violation::textOnEngine($source->path));
        }
    }
}
