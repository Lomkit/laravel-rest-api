<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;

trait Rulable
{
    public function createRules(RestRequest $request) {
        return [];
    }

    public function updateRules(RestRequest $request) {
        return [];
    }
}