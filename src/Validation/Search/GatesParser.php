<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Authorization\Ability;
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

            $abilities[] = new GateAbility($ability);
        }

        return $abilities;
    }

    private function ability(Cursor $entry, Distinct $distinct, ValidationContext $context): ?Ability
    {
        $ability = is_string($entry->value) ? Ability::tryFrom($entry->value) : null;

        if ($ability !== null && $ability->isClassLevel() && $context->includeDepth > 0) {
            $context->rules->add($entry->path, ['prohibited']);

            return null;
        }

        $context->rules->add($entry->path, [Rule::enum(Ability::class), $distinct]);

        return $distinct->accepts($entry->value) ? $ability : null;
    }
}
