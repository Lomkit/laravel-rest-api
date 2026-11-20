<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class SearchParser
{
    public function __construct(
        private SearchShape $shape = SearchShape::Page,
        private bool $paged = true,
    ) {}

    public function parse(Cursor $search, ValidationContext $context): SearchNode
    {
        $context->rules->addWhenPresent($search, ['array']);

        if (! $context->reader->object($search)) {
            return SearchNode::empty();
        }

        $totals = $this->shape === SearchShape::Page && $this->paged;
        $context->reader->refuse($search, [...$this->shape->refused(), ...($totals ? [] : [SearchShape::TOTALS])]);

        $body = $this->shape->body($search, $context);
        $text = $this->text($search->at('text'), $context);

        if ($this->shape !== SearchShape::Aggregate) {
            (new SourceParser)->parse($search->at('source'), $text, $context);
        }

        return $body->node($text, $this->trashed($search->at('trashed'), $context), $totals ? (new TotalsParser)->parse($search->at(SearchShape::TOTALS), $context) : null);
    }

    private function text(Cursor $text, ValidationContext $context): ?TextQuery
    {
        $context->rules->addWhenPresent($text, ['array']);

        if (! $context->reader->object($text)) {
            return null;
        }

        return $context->grammar->text($text, $context);
    }

    private function trashed(Cursor $trashed, ValidationContext $context): ?Trashed
    {
        if (! $context->grammar->trashed($context->resource)) {
            $context->rules->add($trashed->path, [$context->grammar->prohibited()]);

            return null;
        }

        $context->rules->addWhenPresent($trashed, [Rule::enum(Trashed::class)]);
        $parsed = is_string($trashed->value) ? Trashed::tryFrom($trashed->value) : null;

        if ($trashed->present && $parsed === null) {
            $context->rules->partial();
        }

        return $parsed;
    }
}
