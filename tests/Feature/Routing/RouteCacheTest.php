<?php

namespace Lomkit\Rest\Tests\Feature\Routing;

use Illuminate\Routing\CompiledRouteCollection;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\Http\Routing\ResourceRegistrar;
use Lomkit\Rest\Http\Routing\RouteDefinition;
use Lomkit\Rest\Http\Routing\RouteMiddleware;
use Lomkit\Rest\Tests\Concerns\CompilesRoutes;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentController;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RouteCacheTest extends PackageTestCase
{
    use CompilesRoutes;

    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[Test]
    public function it_serves_every_endpoint_from_compiled_routes(): void
    {
        $kept = Document::factory()->create();
        $removed = Document::factory()->create();

        $this->useCompiledRoutes();

        $this->assertInstanceOf(CompiledRouteCollection::class, $this->app['router']->getRoutes());
        $this->getJson('/api/documents')->assertOk();
        $this->postJson('/api/documents/search')->assertOk()->assertJsonCount(2, 'data');
        $this->postJson('/api/documents/actions/archive-documents')->assertOk()->assertExactJson(['data' => ['impacted' => 2]]);
        $this->deleteJson('/api/documents', ['resources' => [$removed->getKey()]])->assertOk();
        $this->postJson('/api/documents/restore', ['resources' => [$removed->getKey()]])->assertOk();
        $this->deleteJson('/api/documents/force', ['resources' => [$kept->getKey()]])->assertOk();
        $this->assertSame([$removed->getKey()], Document::withTrashed()->pluck('id')->all());
    }

    #[Test]
    public function it_keeps_names_and_middleware_through_the_cache(): void
    {
        $this->useCompiledRoutes();

        $route = $this->app['router']->getRoutes()->getByName('documents.operate');

        $this->assertSame('api/documents/actions/{action}', $route?->uri());
        $this->assertSame([EnforceExpectsJson::class], $route->middleware());
    }

    #[Test]
    public function it_keeps_route_defaults_through_the_cache(): void
    {
        Document::factory()->create();
        $this->app->make(ResourceRegistrar::class)->register([new RouteDefinition(
            ['POST'],
            'api/literal-documents/actions/archive-documents',
            'literal-documents.operate',
            DocumentController::class.'@operate',
            new RouteMiddleware([EnforceExpectsJson::class]),
            ['action' => 'archive-documents'],
        )]);

        $this->useCompiledRoutes();

        $this->postJson('/api/literal-documents/actions/archive-documents')->assertOk()->assertExactJson(['data' => ['impacted' => 1]]);
    }
}
