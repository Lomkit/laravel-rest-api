<?php

namespace Lomkit\Rest\Validation\Mutate;

use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Rules\OneOf;
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
        $targets = $this->relation->targets();
        $type = $entry->at('type');
        $typeRules = ['string', new OneOf($targets->types())];
        $rules->gate($entry, $type);

        if ($targets->isPolymorphic()) {
            $rules->add($type->path, ['required', ...$typeRules]);
        } else {
            $rules->addWhenPresent($type, $typeRules);
        }

        $target = $targets->forType($type->value);

        if ($target === null) {
            $rules->partial();

            return null;
        }

        return new EntryTarget($this->relation->resourceOf($target), $target->type);
    }
}
