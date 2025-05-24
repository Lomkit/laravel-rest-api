<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Rules\RestRule;

class SearchInstruction extends RestRule
{
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = app(RestRequest::class);

        $instructionUriKeys = collect(
            $request->isScoutMode() ?
                $this->resource->getScoutInstructions($request) :
                $this->resource->getInstructions($request)
        )
            ->map(function ($instruction) { return $instruction->uriKey(); })
            ->toArray();

        $instruction = $this->resource->instruction($request, $value['name'] ?? '');

        return [
            $attribute.'.name' => [
                Rule::in($instructionUriKeys),
                'required',
                'string',
            ],
            $attribute.'.fields'   => ['sometimes', 'array'],
            $attribute.'.fields.*' => !is_null($instruction) ? [
                (new SearchInstructionField())->setResource($this->resource)->setInstruction($instruction),
            ] : [],
        ];
    }
}
