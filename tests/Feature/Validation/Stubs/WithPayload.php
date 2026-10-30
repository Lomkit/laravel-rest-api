<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class WithPayload extends Instruction
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [Json::make('payload')];
    }

    #[\Override]
    public function handle(array $fields, Builder $query): void
    {
        app(ReceivedFields::class)->record('handle', $fields);
    }
}
