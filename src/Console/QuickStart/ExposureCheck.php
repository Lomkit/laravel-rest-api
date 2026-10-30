<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Lomkit\Rest\Exceptions\InvalidControllerException;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Routing\Endpoint;

final readonly class ExposureCheck
{
    public function __construct(private Router $router, private Gate $gate, private Repository $config) {}

    /** @param  list<string>  $controllers */
    public function run(array $controllers): ExposureReport
    {
        $report = new ExposureReport;

        foreach ($controllers as $controller) {
            $report = $this->checkPolicy($controller, $this->checkRoutes($controller, $report));
        }

        return $report;
    }

    private function checkRoutes(string $controller, ExposureReport $report): ExposureReport
    {
        $routes = array_filter(
            $this->router->getRoutes()->getRoutes(),
            fn (Route $route): bool => ltrim((string) $route->getControllerClass(), '\\') === $controller,
        );

        if ($routes === []) {
            return $report->withProblem("No route uses {$controller}. Register it with Rest::resource() in routes/api.php, and check that bootstrap/app.php loads routes/api.php.");
        }

        foreach ($routes as $route) {
            $problem = $this->authenticationProblem($route);
            $report = $problem === null ? $report->withAuthenticatedRoute() : $report->withProblem($problem);
            $report = $route->getActionMethod() === Endpoint::Search->value ? $report->withSearch($route->uri()) : $report;
        }

        return $report;
    }

    private function authenticationProblem(Route $route): ?string
    {
        $endpoint = implode('|', $route->methods()).' /'.ltrim($route->uri(), '/');
        $guards = $this->guards($route);

        if ($guards === null) {
            return "{$endpoint} is not behind an authentication middleware.";
        }

        foreach ($guards as $guard) {
            if ($this->config->get("auth.guards.{$guard}") === null) {
                return "{$endpoint} authenticates with the [{$guard}] guard, which is not defined.";
            }
        }

        return null;
    }

    /** @return list<string>|null */
    private function guards(Route $route): ?array
    {
        foreach ($this->router->gatherRouteMiddleware($route) as $middleware) {
            [$class, $parameters] = is_string($middleware) ? array_pad(explode(':', $middleware, 2), 2, '') : ['', ''];

            if (is_a($class, Authenticate::class, true)) {
                return $parameters === '' ? [] : explode(',', $parameters);
            }
        }

        return null;
    }

    private function checkPolicy(string $controller, ExposureReport $report): ExposureReport
    {
        $model = $this->modelOf($controller);

        if ($model === null) {
            return $report->withProblem("{$controller} is not a controller with a resource and its model.");
        }

        if ($this->gate->getPolicyFor($model) === null) {
            return $report->withProblem("No policy is found for {$model}. Generate one with php artisan rest:policy, or register it with Gate::policy().");
        }

        return $report->withPolicy();
    }

    private function modelOf(string $controller): ?string
    {
        if (! is_subclass_of($controller, Controller::class)) {
            return null;
        }

        try {
            return $controller::resourceClass()::modelClass();
        } catch (InvalidControllerException|InvalidResourceException) {
            return null;
        }
    }
}
