<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Text\Engine\EngineQuery;

final class BobAndCidReaderInstruction extends Instruction implements AppliesToEngine
{
    #[\Override]
    public function uriKey(): string
    {
        return 'bob-and-cid';
    }

    #[\Override]
    public function handleEngine(InstructionFields $fields, EngineQuery $query): void
    {
        $query->whereIn('readers', ['bob'])->whereIn('readers', ['cid']);
    }
}
