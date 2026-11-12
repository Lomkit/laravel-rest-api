<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;

final readonly class DeclarationCheck
{
    public function __construct(private Container $container) {}

    /** @param list<string> $controllers */
    public function run(array $controllers): void
    {
        $context = new RestContext(RestRequest::createFrom($this->container->make(Request::class)), $this->container);

        foreach (ControllerResources::of($controllers) as $resource) {
            $declarations = $context->resource($resource)->definition()->declarations();
            $declarations->actions()->all();
            $declarations->metrics()->all();
        }
    }
}
