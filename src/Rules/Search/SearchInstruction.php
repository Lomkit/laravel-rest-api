<?php

namespace Lomkit\Rest\Rules\Search;

use Closure;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Rules\Concerns\ValidatesFields;
use Lomkit\Rest\Rules\RestRule;

class SearchInstruction extends RestRule
{
    use ValidatesFields;

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

        $instruction = $this->resource->instruction($request, is_array($value) ? ($value['name'] ?? '') : '');

        return [
            $attribute.'.name' => [
                Rule::in($instructionUriKeys),
                'required',
                'string',
            ],
            $attribute.'.fields'        => ['sometimes', 'array'],
            $attribute.'.fields.*.name' => !is_null($instruction) ? [
                'required',
                'string',
                Rule::in(array_keys($instruction->fields($request))),
            ] : [],
        ];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        parent::validate($attribute, $value, $fail);

        $value = is_array($value) ? $value : [];

        $request = app(RestRequest::class);
        $instruction = $this->resource->instruction($request, $value['name'] ?? '');

        if (!is_null($instruction)) {
            $this->validateFields(
                $this->validator,
                $attribute.'.fields',
                $value['fields'] ?? [],
                $instruction->fields($request)
            );
        }
    }
}
