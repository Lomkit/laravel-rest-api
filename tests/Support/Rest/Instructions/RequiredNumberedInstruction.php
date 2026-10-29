<?php

namespace Lomkit\Rest\Tests\Support\Rest\Instructions;

use Laravel\Scout\Builder;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

class RequiredNumberedInstruction extends Instruction
{
    public function handle(array $fields, \Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('number', '>', $fields['number'] ?? 0);
    }

    public function handleScout(array $fields, Builder $query): void
    {
        //
    }

    public function fields(RestRequest $request): array
    {
        return [
            Integer::make('number')->rules('required'),
        ];
    }
}
