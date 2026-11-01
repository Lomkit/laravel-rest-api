<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Authorization\Authorizer;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Exceptions\InvalidActionStateException;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Http\Requests\Traits\ParsesResourceKeys;
use Lomkit\Rest\Http\Requests\Traits\ParsesSearch;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Rules\Distinct;
use Lomkit\Rest\Rules\Input\InputFields;
use Lomkit\Rest\Rules\MaxItems;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Validation\KeyList;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OperateRequest extends RestRequest
{
    use ParsesResourceKeys;
    use ParsesSearch;

    #[\Override]
    public function endpoint(): Endpoint
    {
        return Endpoint::Operate;
    }

    #[\Override]
    protected function authorizeEntry(Authorizer $authorizer): void
    {
        $resource = $this->rootResource();
        $key = $this->route('action');

        if (! is_string($key) || ! $resource->actionExists($this, $key)) {
            $authorizer->plan([Check::onClass($resource, 'viewAny')]);

            throw new HttpException(404);
        }

        $action = $resource->action($this, $key);

        if ($action->abilities() !== []) {
            $authorizer->plan([Check::onClass($resource, 'viewAny')]);
        }

        $authorizer->enter($resource, self::checksOn($resource, $this->entryAbilities($resource, $action)));
    }

    /** @return list<string> */
    private function entryAbilities(Resource $resource, Action $action): array
    {
        $abilities = $action->abilities();

        return $abilities === [] ? [] : ['viewAny', ...$resource->abilities(Endpoint::Operate, $this), ...$abilities];
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        return $this
            ->resource($this->context()->resource($this->controllerResourceClass()))
            ->operateRules();
    }

    /** @return array<string, mixed> */
    public function operateRules(): array
    {
        if (! $this->resource->actionExists($this, $this->actionKey())) {
            throw new HttpException(404);
        }

        $operatedAction = $this->resource->action($this, $this->actionKey());

        if ($operatedAction->isStandalone() && $operatedAction->isTargeted()) {
            throw new InvalidActionStateException('An action cannot be both standalone and targeted.');
        }

        $fields = $operatedAction->getFields($this);
        $searchable = ! $operatedAction->isStandalone() && ! $operatedAction->isTargeted();

        return [
            ...$searchable
                ? ['search' => ['array'], ...$this->searchRules($this->resource)]
                : ['search' => ['prohibited']],
            ...$operatedAction->isTargeted()
                ? $this->resourceKeyRules($this->resource, $operatedAction->maxResources)
                : ['resources' => ['prohibited']],
            'fields' => [
                'sometimes',
                'array',
                new MaxItems($this->context()->limits()->sizes->fields),
                new InputFields($fields),
            ],
            'fields.*.name' => [
                'required',
                'string',
                Distinct::among($this->input('fields'), 'name'),
                new OneOf($fields->names()),
            ],
        ];
    }

    public function targetKeys(): KeyList
    {
        return $this->resourceKeys();
    }

    /** @return Builder<Model> */
    #[\Override]
    public function keysQuery(Resource $resource): Builder
    {
        $query = $resource::newModel()->newQuery();

        (new PerimeterClause($resource))->apply($query);

        return $query;
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return ['search.text.prohibited' => 'Text search is not available on this resource.'];
    }

    public function actionKey(): string
    {
        $key = $this->route('action');

        if (! is_string($key)) {
            throw new HttpException(404);
        }

        return $key;
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'fields' => $this->input('fields', []),
        ]);
    }

    /** @return array<string, mixed> */
    public function resolveFields(Action $action): array
    {
        return InputValues::fromNamed($action->getFields($this), $this->input('fields', []))->toArray();
    }
}
