<?php

namespace Lomkit\Rest\Validation\Mutate;

use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ParsedRules;

final readonly class MorphToEntryRule implements EntryRule
{
    public function __construct(private PolymorphicRelation $relation) {}

    /** @return list<string> */
    #[\Override]
    public function keys(): array
    {
        return ['type'];
    }

    #[\Override]
    public function target(Cursor $entry, ParsedRules $rules): ?EntryTarget
    {
        $target = $this->relation->targets()->single();

        if ($target === null) {
            $rules->partial();

            return null;
        }

        return new EntryTarget($this->relation->resourceOf($target));
    }
}
