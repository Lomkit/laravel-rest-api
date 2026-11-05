<?php

namespace Lomkit\Rest\Validation\Mutate;

use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ParsedRules;

final readonly class RelatedEntryRule implements EntryRule
{
    public function __construct(private Relation $relation) {}

    /** @return list<string> */
    #[\Override]
    public function keys(): array
    {
        return [];
    }

    #[\Override]
    public function target(Cursor $entry, ParsedRules $rules): EntryTarget
    {
        return new EntryTarget($this->relation->resource());
    }
}
