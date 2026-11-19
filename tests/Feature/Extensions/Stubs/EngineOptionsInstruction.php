<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Text\Engine\EngineQuery;

final class EngineOptionsInstruction extends Instruction implements AppliesToEngine
{
    #[\Override]
    public function uriKey(): string
    {
        return 'options';
    }

    #[\Override]
    public function fields(): array
    {
        return [Json::make('options'), Json::make('excluded_codes')];
    }

    #[\Override]
    public function handleEngine(InstructionFields $fields, EngineQuery $query): void
    {
        $query->options($fields->array('options') ?? [])->whereNotIn('code', $fields->array('excluded_codes') ?? []);
    }
}
