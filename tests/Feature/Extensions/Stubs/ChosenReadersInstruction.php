<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Text\Engine\EngineConditionGroup;
use Lomkit\Rest\Text\Engine\EngineQuery;

final class ChosenReadersInstruction extends Instruction implements AppliesToEngine
{
    #[\Override]
    public function uriKey(): string
    {
        return 'chosen-readers';
    }

    #[\Override]
    public function fields(): array
    {
        return [Json::make('readers')];
    }

    #[\Override]
    public function handleEngine(InstructionFields $fields, EngineQuery $query): void
    {
        $readers = $fields->array('readers') ?? [];

        $query->whereAny(fn (EngineConditionGroup $group): EngineConditionGroup => $group->whereIn('readers', $readers)->where('number', '=', 1));
    }
}
