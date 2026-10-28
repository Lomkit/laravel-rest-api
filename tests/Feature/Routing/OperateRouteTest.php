<?php

namespace Lomkit\Rest\Tests\Feature\Routing;

use Illuminate\Routing\Router;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentController;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\DefineRoute;
use PHPUnit\Framework\Attributes\Test;

class OperateRouteTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[Test]
    public function it_runs_the_action_named_in_the_uri(): void
    {
        Document::factory()->count(2)->create();

        $response = $this->postJson('/api/documents/actions/archive-documents');

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2]]);
        $this->assertSame(2, Document::query()->where('archived', true)->count());
    }

    #[Test]
    #[DefineRoute('tenantPrefixRoutes')]
    public function it_runs_the_action_under_a_prefix_with_a_route_parameter(): void
    {
        Document::factory()->create();

        $response = $this->postJson('/acme/api/documents/actions/archive-documents');

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 1]]);
    }

    #[Test]
    #[DefineRoute('tenantDomainRoutes')]
    public function it_runs_the_action_under_a_domain_with_a_route_parameter(): void
    {
        Document::factory()->create();

        $response = $this->postJson('http://acme.archive.test/tenant-api/documents/actions/archive-documents');

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 1]]);
    }

    #[Test]
    public function it_answers_404_to_an_unknown_action(): void
    {
        $this->postJson('/api/documents/actions/publish-documents')->assertNotFound();
    }

    protected function tenantPrefixRoutes(Router $router): void
    {
        $router->prefix('{tenant}/api')->name('tenant.')->group(function (): void {
            Rest::resource('documents', DocumentController::class);
        });
    }

    protected function tenantDomainRoutes(Router $router): void
    {
        $router->domain('{tenant}.archive.test')->prefix('tenant-api')->name('tenant.')->group(function (): void {
            Rest::resource('documents', DocumentController::class);
        });
    }
}
