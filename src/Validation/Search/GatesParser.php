<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Rules\Distinct;
use Lomkit\Rest\Search\GateAbility;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;
use Lomkit\Rest\Validation\Violation;

final readonly class GatesParser
{
    public function __construct(private bool $classLevel = true) {}

    /** @return list<GateAbility>|null */
    public function parse(Cursor $gates, ValidationContext $context): ?array
    {
        if ($gates->present && $context->includeDepth > 0 && ! $context->resource->isGatingEnabled()) {
            $context->rules->reject(Violation::gatesOff($gates->path));

            return null;
        }

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
        $gates = $resource->definition()->gates();
        $ability = $gates->find($entry->value);

        if ($ability !== null && $ability->isClassLevel() && (! $this->classLevel || $context->includeDepth > 0)) {
            $context->rules->add($entry->path, ['prohibited']);

            return null;
        }

        $context->rules->add($entry->path, [$context->vocabulary->of($resource, $context->grammar, 'gates', $gates->names(...)), $distinct]);

        return $distinct->accepts($entry->value) ? $ability : null;
    }
}
