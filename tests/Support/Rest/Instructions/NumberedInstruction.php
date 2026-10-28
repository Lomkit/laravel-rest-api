<?php

namespace Lomkit\Rest\Tests\Support\Rest\Instructions;

use Laravel\Scout\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

class NumberedInstruction extends Instruction
{
    public function handle(array $fields, \Illuminate\Database\Eloquent\Builder $query)
    {
        $query->where('number', '>', $fields['number'] ?? 0);
    }

    public function handleScout(array $fields, Builder $query)
    {
        //
    }

    public function fields(RestRequest $request): array
    {
        return [
            'number' => [
                'integer',
            ],
        ];
    }
}
