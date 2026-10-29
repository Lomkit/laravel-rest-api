<?php

namespace Lomkit\Rest\Tests\Feature\Routing;

use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\ArchivedDocumentController;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\AuthenticatedDocumentController;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\GuardedDocumentController;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ControllerMiddlewareTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Archive];
    }

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        $router->prefix('api')->group(function (): void {
            Rest::resource('guarded-documents', GuardedDocumentController::class)->only('search', 'destroy');
            Rest::resource('authenticated-documents', AuthenticatedDocumentController::class)->only('search', 'destroy');
            Rest::resource('archived-documents', ArchivedDocumentController::class)->only('search', 'destroy');
        });
    }

    #[Test]
    public function it_gathers_the_middleware_declared_by_attributes_on_the_controller(): void
    {
        $this->assertSame(
            [EnforceExpectsJson::class, 'throttle:5,1'],
            Route::getRoutes()->getByName('guarded-documents.search')?->gatherMiddleware(),
        );
        $this->assertSame(
            [EnforceExpectsJson::class, 'throttle:5,1', Authorize::using('archive')],
            Route::getRoutes()->getByName('guarded-documents.destroy')?->gatherMiddleware(),
        );
    }

    #[Test]
    public function it_runs_the_middleware_declared_by_attributes_on_the_controller(): void
    {
        Gate::define('archive', fn (?Authenticatable $user): bool => false);
        $document = Document::factory()->create(['title' => 'Annual report']);

        $this->postJson('/api/guarded-documents/search')->assertOk()->assertJsonPath('data.0.id', $document->id);
        $this->deleteJson('/api/guarded-documents', ['resources' => [$document->id]])->assertForbidden();
        $this->assertNotSoftDeleted($document);
    }

    #[Test]
    public function it_gathers_the_middleware_a_parent_controller_declares_with_has_middleware(): void
    {
        $this->assertSame(
            [EnforceExpectsJson::class, 'throttle:5,1'],
            Route::getRoutes()->getByName('authenticated-documents.search')?->gatherMiddleware(),
        );
        $this->assertSame(
            [EnforceExpectsJson::class, 'throttle:5,1', 'auth'],
            Route::getRoutes()->getByName('authenticated-documents.destroy')?->gatherMiddleware(),
        );
    }

    #[Test]
    public function it_gathers_the_middleware_a_child_controller_adds_to_its_parent_middleware(): void
    {
        $this->assertSame(
            [EnforceExpectsJson::class, 'throttle:5,1'],
            Route::getRoutes()->getByName('archived-documents.search')?->gatherMiddleware(),
        );
        $this->assertSame(
            [EnforceExpectsJson::class, 'throttle:5,1', 'auth', 'can:archive'],
            Route::getRoutes()->getByName('archived-documents.destroy')?->gatherMiddleware(),
        );
    }

    #[Test]
    public function it_runs_the_middleware_a_parent_controller_declares_with_has_middleware(): void
    {
        $document = Document::factory()->create(['title' => 'Annual report']);

        $this->postJson('/api/authenticated-documents/search')->assertOk()->assertJsonPath('data.0.id', $document->id);
        $this->deleteJson('/api/authenticated-documents', ['resources' => [$document->id]])->assertUnauthorized();
        $this->assertNotSoftDeleted($document);
    }
}
