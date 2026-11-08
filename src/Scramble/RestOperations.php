<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Scramble\Endpoints\EndpointDocumenters;

final readonly class RestOperations implements OperationTransformer
{
    public const string OMITTED = 'lomkit.omitted';

    public const string DROPPED_RESPONSES = 'lomkit.dropped_responses';

    public function __construct(
        private Container $container,
        private OpenApi $document,
    ) {}

    #[\Override]
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $controller = $routeInfo->route->getControllerClass();
        $endpoint = Endpoint::tryFrom($routeInfo->route->getActionMethod());

        if ($controller === null || ! is_subclass_of($controller, Controller::class) || $endpoint === null) {
            return;
        }

        $context = DocumentationContext::guest($routeInfo->route, $routeInfo->method, $endpoint, $controller, $this->container, new ComponentRegistry($this->document->components));

        if ($context->isCatchAll()) {
            $operation->setAttribute(self::OMITTED, true);

            return;
        }

        $operation->setTags([$context->name]);
        $operation->setAttribute(self::DROPPED_RESPONSES, self::references($operation));
        $operation->responses = [];

        EndpointDocumenters::for($endpoint)->document($operation, $context);

        if ($operation->responses === []) {
            $operation->addResponse(self::success());
        }

        (new ErrorResponses($context->components))->attach($operation, $context);
    }

    /** @return list<string> */
    private static function references(Operation $operation): array
    {
        $names = [];

        foreach ($operation->responses ?? [] as $response) {
            if ($response instanceof Reference && $response->referenceType === 'responses' && $response->fullName !== null) {
                $names[] = $response->fullName;
            }
        }

        return $names;
    }

    private static function success(): Response
    {
        return Schemas::response(200, 'Success', new ObjectType);
    }
}
