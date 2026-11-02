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
    private const array KEYS = [
        'text' => true,
        'trashed' => true,
        'filters' => true,
        'gates' => true,
        'scopes' => true,
        'sorts' => true,
        'selects' => true,
        'aggregates' => true,
        'includes' => true,
        'instructions' => true,
        'limit' => true,
    ];

    public function __construct(private bool $paged = true) {}

    public function parse(Cursor $search, ValidationContext $context): SearchNode
    {
        $context->rules->addWhenPresent($search, ['array']);

        if (! $context->reader->object($search)) {
            return SearchNode::empty();
        }

        $context->reader->known($search, $this->paged ? [...self::KEYS, 'page' => true] : self::KEYS);

        $body = SearchBodyParser::keys($search, $context, $this->paged);
        $text = $this->text($search->at('text'), $context);

        return $body->node($text, $this->trashed($search->at('trashed'), $context));
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
            $context->rules->add($trashed->path, ['prohibited']);

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
