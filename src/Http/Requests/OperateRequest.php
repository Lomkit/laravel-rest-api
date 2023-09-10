<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\ActionField;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OperateRequest extends RestRequest
{
    public function rules()
    {
        return $this->operateRules($this->route()->controller::newResource());
    }

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
     * Resolve the request's fields.
     *
     * @return array
     */
    public function resolveFields(Action $action)
    {
        return $this->input('fields', []);
    }
}
