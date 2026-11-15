<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Query\ExtensionRestrictions;
use Lomkit\Rest\Text\Engine\EngineQuery;

final class RowReplacingInstruction extends Instruction implements AppliesToEngine
{
    #[\Override]
    public function uriKey(): string
    {
        return 'replace-rows';
    }

    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void {}

    #[\Override]
    public function handleEngine(InstructionFields $fields, EngineQuery $query): void
    {
        $rows = ExtensionRestrictions::of($this->resource);

        if ($rows !== null) {
            $query->restrictRows($rows);
        }
    }
}
