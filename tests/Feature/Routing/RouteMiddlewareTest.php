<?php

namespace Lomkit\Rest\Tests\Feature\Routing;

use Closure;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route as RouteFacade;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\Http\Routing\PendingResourceRegistration;
use Lomkit\Rest\Tests\Concerns\InspectsResourceRoutes;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentController;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Attributes\DefineRoute;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RouteMiddlewareTest extends PackageTestCase
{
    use InspectsResourceRoutes;

    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[Test]
    public function it_puts_enforce_expects_json_on_every_route(): void
    {
        foreach ($this->registerDocuments()->getRoutes() as $route) {
            $this->assertSame([EnforceExpectsJson::class], $route->middleware());
        }
    }

    #[Test]
    public function it_appends_resource_middleware_after_the_package_middleware(): void
    {
        $routes = $this->registerDocuments(fn (PendingResourceRegistration $pending) => $pending->middleware(['auth', 'verified']));

        $this->assertSame([EnforceExpectsJson::class, 'auth', 'verified'], $this->route($routes->getRoutes(), 'documents.search')->middleware());
    }

    #[Test]
    public function it_adds_up_repeated_resource_middleware(): void
    {
        $routes = $this->registerDocuments(fn (PendingResourceRegistration $pending) => $pending->middleware('auth')->middleware('verified'));

        $this->assertSame([EnforceExpectsJson::class, 'auth', 'verified'], $this->route($routes->getRoutes(), 'documents.details')->middleware());
    }

    #[Test]
    #[DefineEnvironment('enablePrecognition')]
    public function it_keeps_both_package_middleware_when_precognition_is_enabled(): void
    {
        $routes = $this->registerDocuments(fn (PendingResourceRegistration $pending) => $pending->middleware('auth'));

        $this->assertSame(
            [EnforceExpectsJson::class, HandlePrecognitiveRequests::class, 'auth'],
            $this->route($routes->getRoutes(), 'documents.mutate')->middleware(),
        );
    }

    /** @param Closure(PendingResourceRegistration): mixed $configure */
    #[Test]
    #[DataProvider('layerOrders')]
    public function it_orders_middleware_by_layer_whatever_the_call_order(Closure $configure): void
    {
        $routes = $this->registerDocuments($configure)->getRoutes();

        $this->assertSame([EnforceExpectsJson::class, 'auth', 'throttle:5,1'], $this->route($routes, 'documents.operate')->middleware());
        $this->assertSame([EnforceExpectsJson::class, 'auth'], $this->route($routes, 'documents.details')->middleware());
    }

    /** @return iterable<string, array{Closure(PendingResourceRegistration): mixed}> */
    public static function layerOrders(): iterable
    {
        yield 'endpoint first' => [fn (PendingResourceRegistration $pending) => $pending->middlewareFor('operate', 'throttle:5,1')->middleware('auth')];
        yield 'resource first' => [fn (PendingResourceRegistration $pending) => $pending->middleware('auth')->middlewareFor('operate', 'throttle:5,1')];
    }

    #[Test]
    public function it_adds_up_repeated_middleware_for_one_endpoint(): void
    {
        $routes = $this->registerDocuments(fn (PendingResourceRegistration $pending) => $pending
            ->middlewareFor('search', 'auth')->middlewareFor(['search', 'mutate'], 'throttle:5,1'));

        $this->assertSame([EnforceExpectsJson::class, 'auth', 'throttle:5,1'], $this->route($routes->getRoutes(), 'documents.search')->middleware());
        $this->assertSame([EnforceExpectsJson::class, 'throttle:5,1'], $this->route($routes->getRoutes(), 'documents.mutate')->middleware());
    }

    #[Test]
    public function it_excludes_middleware_per_resource_and_per_endpoint(): void
    {
        $routes = $this->registerDocuments(fn (PendingResourceRegistration $pending) => $pending
            ->withoutMiddleware('auth')->withoutMiddlewareFor('operate', 'throttle:5,1'));

        $this->assertSame(['auth'], $this->route($routes->getRoutes(), 'documents.details')->excludedMiddleware());
        $this->assertSame(['auth', 'throttle:5,1'], $this->route($routes->getRoutes(), 'documents.operate')->excludedMiddleware());
    }

    #[Test]
    public function it_runs_group_middleware_first(): void
    {
        RouteFacade::middleware('auth')->group(function (): void {
            Rest::resource('papers', DocumentController::class)->only('search')->middleware('verified');
        });

        $this->assertSame(['auth', EnforceExpectsJson::class, 'verified'], RouteFacade::getRoutes()->getByName('papers.search')?->middleware());
    }

    #[Test]
    #[DefineRoute('jsonApiRoutes')]
    public function it_answers_json_without_accept_header_when_resource_middleware_is_set(): void
    {
        $response = $this->post('/json-api/documents/search', ['search' => ['filters' => [['field' => 'author', 'value' => 'Ada']]]]);

        $response->assertUnprocessable()->assertJsonValidationErrors('search.filters.0.field');
    }

    protected function enablePrecognition($app): void
    {
        $app['config']->set('rest.precognition.enabled', true);
    }

    protected function jsonApiRoutes(Router $router): void
    {
        $router->prefix('json-api')->name('json-api.')->group(function (): void {
            Rest::resource('documents', DocumentController::class)->only('search')->middleware('throttle:60,1');
        });
    }

    /** @param array<int, Route> $routes */
    private function route(array $routes, string $name): Route
    {
        foreach ($routes as $route) {
            if ($route->getName() === $name) {
                return $route;
            }
        }

        $this->fail("The route [{$name}] is not registered.");
    }
}
