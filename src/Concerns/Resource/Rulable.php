<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Rulable
{
    public function rules(RestRequest $request)
    {
        return [];
    }

    public function createRules(RestRequest $request)
    {
        return [];
    }

    public function updateRules(RestRequest $request)
    {
        return [];
    }
}
