<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\Operate\OperateFields;
use Lomkit\Rest\Rules\Search\Search;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OperateRequest extends RestRequest
{
    /**
     * Define the validation rules for the operate request.
     *
     * @return array
     *
     * This method defines the validation rules for resource operations.
     * It checks if the requested action exists for the given resource and
     * includes validation for fields related to the operation.
     */
    public function rules()
    {
        return $this
            ->resource($this->route()->controller::newResource())
            ->operateRules();
    }

    /**
     * Define the validation rules for resource operations.
     *
     * @return array
     *
     * This method specifies the validation rules for resource operations.
     * It checks if the requested action exists for the given resource, and if so,
     * it includes validation rules for fields associated with the operation.
     */
    public function operateRules()
    {
        if (!$this->resource->actionExists($this, $this->route()->parameter('action'))) {
            throw new HttpException(404);
        }

        $operatedAction = $this->resource->action($this, $this->route()->parameter('action'));

        return array_merge(
            $operatedAction->isStandalone() ? [
                'search' => [
                    'prohibited',
                ],
            ] : [
                'search' => [(new Search())->setResource($this->resource)],
            ],
            [
                'fields' => [
                    'sometimes',
                    'array',
                    (new OperateFields())->setAction($operatedAction),
                ],
                'fields.*.name' => [
                    Rule::in(array_keys($operatedAction->fields($this))),
                ],
            ]
        );
    }

    /**
     * Prepare the data for validation.
     *
     * Ensures the fields key is always present so the OperateFields rule runs
     * even when the client omits it entirely, allowing required rules to fire.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'fields' => $this->input('fields', []),
        ]);
    }

    /**
     * Resolve the request's fields for a specific action.
     *
     * @return array
     *
     * This method resolves the fields for the current request based on the action being performed.
     */
    public function resolveFields(Action $action)
    {
        return $this->input('fields', []);
    }
}
