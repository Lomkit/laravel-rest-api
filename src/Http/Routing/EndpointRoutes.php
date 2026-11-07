<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;

final readonly class EndpointRoutes
{
    /**
     * @param  class-string<Controller>  $controller
     * @param  array<string, RouteMiddleware>  $endpointMiddleware
     */
    public function __construct(
        private UriSegment $name,
        private string $controller,
        private ResourceVerbs $verbs,
        private RouteNames $names,
        private RouteMiddleware $middleware,
        private array $endpointMiddleware,
        private Container $container,
    ) {}

    /**
     * @param  list<Endpoint>  $endpoints
     * @return list<RouteDefinition>
     */
    public function definitions(array $endpoints): array
    {
        $definitions = [];

        foreach ($endpoints as $endpoint) {
            array_push($definitions, ...$this->for($endpoint));
        }

        return $definitions;
    }

    /** @return list<RouteDefinition> */
    private function for(Endpoint $endpoint): array
    {
        $route = new RouteDefinition(
            $endpoint->methods(),
            $endpoint->uri($this->name, $this->verbs),
            $this->names->for($endpoint),
            $this->controller.'@'.$endpoint->value,
            $this->middlewareFor($endpoint),
        );

        if ($endpoint !== Endpoint::Operate) {
            return [$route];
        }

        return [...array_map(fn (Action $action): RouteDefinition => $this->actionRoute($route, $action), $this->actions()), $route];
    }

    private function actionRoute(RouteDefinition $operate, Action $action): RouteDefinition
    {
        $key = $action->uriKey();

        return new RouteDefinition(
            $operate->methods,
            Endpoint::Operate->uri($this->name, $this->verbs, $key),
            $operate->name.'.'.$key,
            $operate->uses,
            $operate->middleware->with($action->middleware)->without($action->withoutMiddleware),
            ['action' => $key],
        );
    }

    /** @return list<Action> */
    private function actions(): array
    {
        if (! is_subclass_of($this->controller, Controller::class)) {
            return [];
        }

        $context = new RestContext(RestRequest::createFrom($this->container->make(Request::class)), $this->container);

        return array_values($context
            ->resource($this->controller::resourceClass())
            ->definition()
            ->actions());
    }

    private function middlewareFor(Endpoint $endpoint): RouteMiddleware
    {
        return $this->middleware->merge($this->endpointMiddleware[$endpoint->value] ?? new RouteMiddleware);
    }
}
