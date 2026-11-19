<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Text\Engine\EngineQuery;

final class NamePrefixes extends Instruction implements AppliesToEngine
{
    #[\Override]
    public function uriKey(): string
    {
        return 'name-prefixes';
    }

    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void {}

    #[\Override]
    public function handleEngine(InstructionFields $fields, EngineQuery $query): void
    {
        $query->whereIn('name_prefixes', ['re', 'rep']);
    }
}
