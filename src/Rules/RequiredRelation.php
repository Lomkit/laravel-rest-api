<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\InvokableRule;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Http\Requests\RestRequest;

class RequiredRelation implements InvokableRule
{

    use Makeable;

    /**
     * The resource related to.
     *
     * @var Resource
     */
    protected $resource = null;

    /**
     *
     *
     * @param $resource
     * @return $this
     */
    public function resource($resource)
    {
        $this->resource = $resource;

        return $this;
    }

    protected function isOperationRequired(string $operation) {
        return in_array($operation, ['create']);
    }

    public function __invoke($attribute, $value, $fail)
    {
        $request = app()->make(RestRequest::class);

        $requiredRelations = collect(
            $this->resource
                ->getRelations($request)
        )->filter(
            function ($relation) use ($request) {
                return $relation->isRequiredOnCreation($request);
            }
        )->mapWIthKeys(
            function ($relation) {
                return [$relation->relation => $relation];
            }
        );

        if (!empty(array_diff_key($requiredRelations->toArray(), $value))) {
            $relationsToString = $requiredRelations->implode(
                function ($item, $key) {
                    return $key;
                },
                ', '
            );

            $fail(sprintf('The %s relations are required.', $relationsToString));
        }
    }
}