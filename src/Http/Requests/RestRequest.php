<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Http\Requests\Traits\InteractsWithRules;
use Lomkit\Rest\Http\ResourceRegistry;

class RestRequest extends FormRequest
{
    use InteractsWithRules;
    use Resourcable;

    private ?ResourceRegistry $resources = null;

    public function validateResolved(): void
    {
        $this->container->instance(self::class, $this);

        parent::validateResolved();
    }

    public function resources(): ResourceRegistry
    {
        return $this->resources ??= new ResourceRegistry($this->container);
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
