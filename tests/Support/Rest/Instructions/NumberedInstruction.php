<?php

namespace Lomkit\Rest\Tests\Support\Rest\Instructions;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

class NumberedInstruction extends Instruction
{
    public function handle(array $fields, Builder $query): void
    {
        $query->where('number', '>', $fields['number'] ?? 0);
    }

    public function fields(RestRequest $request): array
    {
        return [
            Integer::make('number'),
        ];
    }
}
