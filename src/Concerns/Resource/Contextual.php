<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;

trait Contextual
{
    private ?RestContext $context = null;

    public function withinContext(RestContext $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function context(): RestContext
    {
        return $this->context ??= new RestContext(RestRequest::createFrom(request()), app());
    }
}
