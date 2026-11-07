<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;

class ScoutHandledInstruction extends Instruction
{
    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void {}

    /** @param array<string, mixed> $fields */
    public function handleScout(array $fields, object $query): void {}
}
