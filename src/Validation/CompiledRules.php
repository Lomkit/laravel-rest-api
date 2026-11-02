<?php

namespace Lomkit\Rest\Validation;

use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Fields\WriteRules;

final readonly class CompiledRules
{
    /**
     * @param  array<string, list<mixed>>  $rules
     * @param  array<string, mixed>  $projections
     * @param  list<Check>  $reach
     */
    public function __construct(
        public array $rules,
        public MessageBook $book,
        public array $projections,
        public array $reach = [],
    ) {}

    /** @param array<array-key, mixed> $rules */
    public static function of(ParsedRules $parsed, array $rules, NodeBudget $budget): self
    {
        $explicit = [];
        $wildcards = [];

        foreach ($parsed->merge($rules) as $key => $list) {
            if (str_contains((string) $key, '*')) {
                $wildcards[(string) $key] = $list;

                continue;
            }

            $explicit[(string) $key] = WriteRules::listOf($list);
        }

        $wildcards = array_map(WriteRules::listOf(...), $budget->open($wildcards));
        $parsed->book()->written(array_keys($parsed->rules()));

        return new self($budget->applyTo($explicit + $wildcards), $parsed->book(), $parsed->projections(), $parsed->checks());
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    public function data(array $input): array
    {
        foreach ($this->projections as $key => $value) {
            KeyPath::set($input, $key, $value);
        }

        return $input;
    }

    public function configure(Validator $validator): void
    {
        $translated = $validator->getTranslator()->get('validation.attributes');

        $validator->setAttributeNames($this->book->attributeNames($validator->customAttributes, is_array($translated) ? Arr::dot($translated) : []));
        $validator->setCustomMessages(array_diff_key($this->book->messages(), $validator->customMessages));
    }
}
