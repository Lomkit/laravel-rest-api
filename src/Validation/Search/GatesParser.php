<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Rules\Distinct;
use Lomkit\Rest\Search\GateAbility;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class GatesParser
{
    /** @return list<GateAbility>|null */
    public function parse(Cursor $gates, ValidationContext $context): ?array
    {
        $context->rules->addWhenPresent($gates, ['array']);
        $entries = $context->reader->entries($gates, PHP_INT_MAX);

        if ($entries === null) {
            return null;
        }

        $distinct = new Distinct(array_map(static fn (Cursor $entry): mixed => $entry->value, $entries));
        $abilities = [];

        foreach ($entries as $entry) {
            $ability = $this->ability($entry, $distinct, $context);

            if ($ability === null) {
                $context->rules->partial();

                continue;
            }

            $abilities[] = $ability;
        }

        return $abilities;
    }

    private function ability(Cursor $entry, Distinct $distinct, ValidationContext $context): ?GateAbility
    {
        $resource = $context->resource;
        $gates = $resource->context()->definition($resource)->gates();
        $ability = $gates->find($entry->value);

        if ($ability !== null && $ability->isClassLevel() && $context->includeDepth > 0) {
            $context->rules->add($entry->path, ['prohibited']);

            return null;
        }

        $context->rules->add($entry->path, [$context->vocabulary->of($resource, $context->grammar, 'gates', $gates->names(...)), $distinct]);

        return $distinct->accepts($entry->value) ? $ability : null;
    }
}
