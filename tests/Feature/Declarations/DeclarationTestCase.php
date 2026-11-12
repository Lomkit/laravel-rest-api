<?php

namespace Lomkit\Rest\Tests\Feature\Declarations;

use Illuminate\Routing\ResourceRegistrar as IlluminateResourceRegistrar;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeclarationLog;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketController;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketResource;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\ToggledQueuedAction;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use ReflectionProperty;

abstract class DeclarationTestCase extends HelpdeskTestCase
{
    /** @var array<string, string> */
    private array $laravelVerbs = [];

    #[Before]
    public function resetDeclarations(): void
    {
        $this->laravelVerbs = IlluminateResourceRegistrar::verbs();
        DeskTicketResource::resetDeclarations();
        DeclarationLog::reset();
        ToggledQueuedAction::$guarded = false;
    }

    #[After]
    public function restoreDeclarations(): void
    {
        (new ReflectionProperty(IlluminateResourceRegistrar::class, 'verbs'))->setValue(null, $this->laravelVerbs);
        DeskTicketResource::resetDeclarations();
        DeclarationLog::reset();
        ToggledQueuedAction::$guarded = false;
    }

    /** @return array<string, array{string, array<string, mixed>, list<mixed>, list<string>}> */
    protected function deskRoutes(string $endpoint): array
    {
        $routes = [];

        foreach ($this->app['router']->getRoutes()->getRoutes() as $route) {
            if ($route->getActionName() === DeskTicketController::class.'@'.$endpoint) {
                $routes[$route->getName() ?? ''] = [
                    implode('|', $route->methods()).' '.$route->uri(),
                    $route->defaults,
                    $route->middleware(),
                    $route->excludedMiddleware(),
                ];
            }
        }

        return $routes;
    }
}
