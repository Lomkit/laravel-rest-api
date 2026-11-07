<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class WithPayload extends Instruction
{
    #[\Override]
    public function fields(): array
    {
        return [Json::make('payload')];
    }

    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        app(ReceivedFields::class)->record('handle', $fields->toArray());
    }
}
