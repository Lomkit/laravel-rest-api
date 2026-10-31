<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Instructions\Instruction;

class WithLines extends Instruction
{
    #[\Override]
    public function handle(array $fields, Builder $query): void
    {
        $query->has('lines');
    }
}
