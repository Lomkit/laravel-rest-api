<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Exceptions\InvalidActionStateException;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Rules\Operate\OperateFields;
use Lomkit\Rest\Rules\Search\Search;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OperateRequest extends RestRequest
{
    /** @return array<string, mixed> */
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

        $model = $this->resource::newModel();

        return array_merge(
            [
                'search' => $operatedAction->isStandalone() || $operatedAction->isTargeted()
                    ? ['prohibited']
                    : [(new Search)->setResource($this->resource)],
                'resources' => $operatedAction->isTargeted()
                    ? ['required', 'array', 'max:'.$operatedAction->maxResources]
                    : ['prohibited'],
                'resources.*' => $operatedAction->isTargeted()
                    ? [Rule::exists($model::class, $model->getKeyName())]
                    : [],
            ],
            [
                'fields' => [
                    'sometimes',
                    'array',
                    (new OperateFields)->setAction($operatedAction),
                ],
                'fields.*.name' => [
                    'required',
                    'string',
                    Rule::in($operatedAction->getFields($this)->names()),
                ],
            ]
        );
    }

    public function actionKey(): string
    {
        $key = $this->route('action');

        if (! is_string($key)) {
            throw new HttpException(404);
        }

        return $key;
    }

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
