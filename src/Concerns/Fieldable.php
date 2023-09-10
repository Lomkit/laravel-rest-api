<?php

namespace Lomkit\Rest\Concerns;

trait Fieldable
{
    /**
     * The fields.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest $request
     *
     * @return array
     */
    public function fields(\Lomkit\Rest\Http\Requests\RestRequest $request): array
    {
        return [];
    }

    /**
     * The fields.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest $request
     * @param string                                 $name
     *
     * @return array
     */
    public function field(\Lomkit\Rest\Http\Requests\RestRequest $request, string $name)
    {
        return collect($this->fields($request))
            ->first(function ($value, $fieldName) use ($name) { return $fieldName === $name; });
    }
}
