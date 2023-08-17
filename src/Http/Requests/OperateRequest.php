<?php

namespace Lomkit\Rest\Http\Requests;

use Closure;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasManyThrough;
use Lomkit\Rest\Relations\MorphedByMany;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Rules\CustomRulable;
use Lomkit\Rest\Rules\Includable;
use Lomkit\Rest\Rules\RequiredRelation;
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
                'fields' => [
                    'array:'.implode(',', array_keys($operatedAction->fields($this)))
                ]
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
