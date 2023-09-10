<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\ActionField;
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
        return $this->operateRules($this->route()->controller::newResource());
    }

    /**
     * Define the validation rules for resource operations.
     *
     * @param resource $resource
     *
     * @return array
     *
     * This method specifies the validation rules for resource operations.
     * It checks if the requested action exists for the given resource, and if so,
     * it includes validation rules for fields associated with the operation.
     */
    public function operateRules(Resource $resource)
    {
        if (!$resource->actionExists($this, $this->route()->parameter('action'))) {
            throw new HttpException(404);
        }

        $operatedAction = $resource->action($this, $this->route()->parameter('action'));

        return array_merge(
            app(SearchRequest::class)->searchRules($resource, 'search'),
            [
                'fields.*.name' => [
                    Rule::in(array_keys($operatedAction->fields($this))),
                ],
                'fields' => [
                    'sometimes',
                    'array',
                ],
                'fields.*' => [
                    ActionField::make()
                        ->action($operatedAction),
                ],
            ]
        );
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
