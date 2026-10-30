<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\Traits\ValidatesResourceKeys;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;

class DestroyRequest extends RestRequest
{
    use ValidatesResourceKeys;

    #[\Override]
    public function endpoint(): Endpoint
    {
        return Endpoint::Destroy;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        return $this->destroyRules($this->context()->resource($this->controllerResourceClass()));
    }

    /** @return array<string, mixed> */
    public function destroyRules(Resource $resource): array
    {
        $model = $resource::newModel();

        return [
            'resources' => [
                'required', 'array',
            ],
            'resources.*' => [
                'distinct',
                ...$this->keyRules($resource),
                Rule::exists($model::class, $model->getKeyName()),
            ],
            'search' => ['bail', 'prohibited', 'array'],
        ];
    }
}
