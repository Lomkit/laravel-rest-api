<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class NestedRelation implements ValidationRule, ValidatorAwareRule
{
    use Makeable;

    /**
     * The validator instance.
     *
     * @var \Illuminate\Validation\Validator
     */
    protected \Illuminate\Validation\Validator $validator;

    /**
     * The resource instance.
     *
     * @var resource
     */
    protected Resource $resource;

    /**
     * If the rules is specified at root level.
     *
     * @var bool
     */
    protected bool $isRootSearchRules;

    public function __construct(Resource $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Set the current validator.
     */
    public function setValidator(\Illuminate\Validation\Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $relation = $value;
        $relationResource = $this->resource;

        do {
            $relationResource = $relationResource->relationResource(Str::before($relation, '.'));

            if ($relationResource === null) {
                $fail('The relation is not allowed');
                break;
            }

            $relation = Str::after($relation, '.');
        } while (Str::contains($relation, '.'));
    }
}
