<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Lomkit\Rest\Http\Controllers\Controller;

final readonly class RegisteredControllers
{
    public function __construct(private Router $router) {}

    /** @return list<string> */
    public function all(): array
    {
        $controllers = array_map(
            fn (Route $route): string => ltrim((string) $route->getControllerClass(), '\\'),
            $this->router->getRoutes()->getRoutes(),
        );

        return array_values(array_unique(array_filter($controllers, fn (string $controller): bool => is_subclass_of($controller, Controller::class))));
    }
}
