<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Support\Str;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Search\SortNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class SortParser
{
    private const array KEYS = ['field' => true, 'direction' => true];

    /**
     * @param  list<Cursor>  $entries
     * @return list<SortNode>
     */
    public function parse(array $entries, int $count, ValidationContext $context): array
    {
        if ($entries === []) {
            return [];
        }

        $fields = $context->grammar->sortFields($context, $count);
        $sorts = [];

        foreach ($entries as $entry) {
            $sort = $this->sort($entry, $fields, $context);

            if ($sort !== null) {
                $sorts[] = $sort;
            }
        }

        return $sorts;
    }

    private function sort(Cursor $entry, OneOf $fields, ValidationContext $context): ?SortNode
    {
        if (! $context->reader->object($entry)) {
            return null;
        }

        $context->reader->known($entry, self::KEYS);

        $field = $entry->at('field');
        $direction = $entry->at('direction');

        $context->rules->add($field->path, [$fields, 'required', 'string']);
        $context->rules->addWhenPresent($direction, [$context->grammar->sortDirection($field->value)]);

        if (is_string($field->value) && str_contains($field->value, '.')) {
            $context->reachRelation(Str::beforeLast($field->value, '.'), $entry->path);
        }

        $parsed = is_string($direction->value) ? SortDirection::tryFrom($direction->value) : null;

        if (! is_string($field->value) || ! $fields->accepts($field->value) || ($direction->present && $parsed === null)) {
            $context->rules->partial();

            return null;
        }

        return $context->grammar->sort($field->value, $parsed, $context);
    }
}
