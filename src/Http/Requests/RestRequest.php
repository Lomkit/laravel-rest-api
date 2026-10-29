<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Http\Requests\Traits\InteractsWithRules;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;

class RestRequest extends FormRequest
{
    use InteractsWithRules;
    use Resourcable;

    private ?RestContext $context = null;

    public function context(): RestContext
    {
        return $this->context ??= new RestContext($this, $this->container);
    }

    public function isScoutMode(): bool
    {
        return $this->has('search.text.value');
    }

    /** @return class-string<Resource> */
    protected function controllerResourceClass(): string
    {
        return $this->route()?->controller::resourceClass();
    }
}
