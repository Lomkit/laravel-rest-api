<?php

namespace Lomkit\Rest\Tests\Support\Rest\Instructions;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

class ConditionalInstruction extends Instruction
{
    public function handle(array $fields, \Illuminate\Database\Eloquent\Builder $query)
    {
        // No-op refinement — used only to exercise field validation.
    }

    public function handleScout(array $fields, \Laravel\Scout\Builder $query)
    {
        //
    }

    public function fields(RestRequest $request): array
    {
        return [
            'type'   => ['nullable', 'string'],
            'reason' => ['required_if:type,ban', 'string'],
        ];
    }
}
