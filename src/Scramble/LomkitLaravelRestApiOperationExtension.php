<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Scramble\Endpoints\EndpointDocumenters;

class LomkitLaravelRestApiOperationExtension extends OperationExtension
{
    #[\Override]
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $controller = $routeInfo->route->getController();

        if (! $controller instanceof Controller) {
            return;
        }

        $context = new RestContext(RestRequest::createFrom(request()), app());
        $resource = $context->resource($controller::resourceClass());
        $name = str_replace('Resource', '', class_basename($resource));

        $operation->setTags([$name]);

        $endpoint = Endpoint::tryFrom($routeInfo->route->getActionMethod());

        if ($endpoint === null) {
            return;
        }

        EndpointDocumenters::for($endpoint)?->document($operation, $resource, $name);
    }
}
