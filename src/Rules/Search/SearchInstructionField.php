<?php

namespace Lomkit\Rest\Rules\Search;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Rules\RestRule;

class SearchInstructionField extends RestRule
{
    protected Instruction $instruction;

    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = app(RestRequest::class);

        $field = $this->instruction->field($request, $value['name'] ?? '') ?? [];

        return [
            $attribute.'.name' => [
                'required',
                'string',
                \Illuminate\Validation\Rule::in(array_keys($this->instruction->fields($request))),
            ],
            $attribute.'.value' => $field,
        ];
    }

    public function setInstruction(Instruction $instruction)
    {
        $this->instruction = $instruction;

        return $this;
    }
}
