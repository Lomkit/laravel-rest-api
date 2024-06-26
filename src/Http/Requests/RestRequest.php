<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Http\Requests\Traits\InteractsWithRules;

class RestRequest extends FormRequest
{
    use InteractsWithRules;
    use Resourcable;

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
