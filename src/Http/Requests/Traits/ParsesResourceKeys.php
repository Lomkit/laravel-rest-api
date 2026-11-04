<?php

namespace Lomkit\Rest\Http\Requests\Traits;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\KeyList;
use Lomkit\Rest\Validation\KeyRead;
use Lomkit\Rest\Validation\KeysGrammar;
use Lomkit\Rest\Validation\Violations;

trait ParsesResourceKeys
{
    private ?KeyList $keyList = null;

    private ?Violations $keyViolations = null;

    /** @return EloquentBuilder<Model> */
    abstract public function keysQuery(Resource $resource): EloquentBuilder;

    /** @return array<string, list<mixed>> */
    protected function resourceKeyRules(Resource $resource, int $max): array
    {
        $violations = new Violations;

        $this->keyList = KeysGrammar::of($resource, $this, fn (): EloquentBuilder => $this->keysQuery($resource), $max, KeyRead::Rows)
            ->parse(Cursor::of($this->all(), $violations)->at('resources'));
        $this->keyViolations = $violations;

        if ($violations->has('resources')) {
            $this->budget()->close('resources');
        }

        return $violations->applyTo(['resources' => ['required', 'array'], ...$this->keyList->rules]);
    }

    protected function resourceKeys(): KeyList
    {
        if ($this->hasPassedValidation() && $this->keyList === null) {
            throw new LogicException('The request has no resource keys.');
        }

        if ($this->keyList === null || $this->keyViolations?->isEmpty() !== true || ! $this->hasPassedValidation()) {
            throw new LogicException('The resource keys were read before the request passed validation.');
        }

        return $this->keyList;
    }
}
