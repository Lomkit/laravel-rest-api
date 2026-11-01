<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Instructions\Instruction;

class ScoutHandledInstruction extends Instruction
{
    #[\Override]
    public function handle(array $fields, Builder $query): void {}

    /** @param array<string, mixed> $fields */
    public function handleScout(array $fields, object $query): void {}
}
