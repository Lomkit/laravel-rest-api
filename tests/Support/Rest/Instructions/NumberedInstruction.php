<?php

namespace Lomkit\Rest\Tests\Support\Rest\Instructions;

use Lomkit\Rest\Instructions\Instruction;

class NumberedInstruction extends Instruction
{
    public function handle(array $fields, \Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where('number', '>', $fields['number'] ?? 0);
    }

    public function fields(\Lomkit\Rest\Http\Requests\RestRequest $request): array
    {
        return [
            'number' => [
                'integer'
            ]
        ];
    }
}