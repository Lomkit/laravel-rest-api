<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lomkit\Rest\Http\Requests\Traits\InteractsWithRules;
use Lomkit\Rest\Http\Resource;

class RestRequest extends FormRequest
{
    use InteractsWithRules;

    /**
     * The resource the request is linked to.
     *
     * @var Resource
     */
    public Resource $resource;

    public function resource(Resource $resource) {
        return tap($this, function() use ($resource) {
            $this->resource = $resource;
        });
    }
}
