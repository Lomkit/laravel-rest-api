<?php

namespace Lomkit\Rest\Validation\Mutate;

use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ParsedRules;

interface EntryRule
{
    /** @return list<string> */
    public function keys(): array;

    public function target(Cursor $entry, ParsedRules $rules): ?EntryTarget;
}
