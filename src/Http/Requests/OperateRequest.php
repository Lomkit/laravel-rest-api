<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Exceptions\InvalidActionStateException;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Rules\OneOf;
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
        $searchable = ! $operatedAction->isStandalone() && ! $operatedAction->isTargeted();

        return array_merge(
            [
                'search' => $searchable
                    ? ['array', (new Search)->setResource($this->resource)]
                    : ['prohibited'],
                'search.text' => $searchable ? ['prohibited'] : [],
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
                    'distinct',
                    new OneOf($operatedAction->getFields($this)->names()),
                ],
            ]
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['search.text.prohibited' => 'Text search is not available for actions.'];
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
