<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Rulable
{
    /** @return array<string, mixed> */
    public function rules(RestRequest $request): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function createRules(RestRequest $request): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function updateRules(RestRequest $request): array
    {
        return [];
    }
}
