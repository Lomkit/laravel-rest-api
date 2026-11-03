<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class GatesParser
{
    private const array GATES = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'];

    /** @return list<string>|null */
    public function parse(Cursor $gates, ValidationContext $context): ?array
    {
        $context->rules->addWhenPresent($gates, ['array', Rule::in(self::GATES)]);
        $entries = $context->reader->entries($gates, $context->limits->sizes->gates);

        if (! $gates->present) {
            return null;
        }

        $known = [];

        foreach ($entries ?? [] as $entry) {
            if (is_string($entry->value) && in_array($entry->value, self::GATES, true)) {
                $known[] = $entry->value;
            }
        }

        if ($entries === null || count($known) !== count($entries)) {
            $context->rules->partial();
        }

        return $known;
    }
}
