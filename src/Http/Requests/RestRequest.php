<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Http\Requests\Traits\InteractsWithRules;
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

    /**
     * Determine if scout mode is asked for the given request.
     *
     * @var bool
     */
    public function isScoutMode()
    {
        return $this->has('search.text.value');
    }
}
