<?php

namespace Lomkit\Rest\Concerns;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Fieldable
{
    /**
     * The fields.
     */
    public function fields(RestRequest $request): array
    {
        return [];
    }

    /**
     * The fields.
     *
     *
     * @return array
     */
    public function field(RestRequest $request, string $name)
    {
        return collect($this->fields($request))
            ->first(function ($value, $fieldName) use ($name) {
                return $fieldName === $name;
            });
    }
}
