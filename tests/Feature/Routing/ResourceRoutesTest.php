<?php

namespace Lomkit\Rest\Tests\Feature\Routing;

use Closure;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Http\Routing\PendingResourceRegistration;
use Lomkit\Rest\Tests\Concerns\InspectsResourceRoutes;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentController;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ResourceRoutesTest extends PackageTestCase
{
    use InspectsResourceRoutes;

    #[Test]
    public function it_registers_the_default_endpoints_in_catalogue_order(): void
    {
        $this->assertSame([
            'documents.details' => 'GET|HEAD documents',
            'documents.search' => 'POST documents/search',
            'documents.mutate' => 'POST documents/mutate',
            'documents.operate' => 'POST documents/actions/{action}',
            'documents.destroy' => 'DELETE documents',
        ], $this->table($this->registerDocuments()));
    }

    #[Test]
    public function it_points_each_route_to_the_controller_method_of_its_endpoint(): void
    {
        $actions = array_map(
            fn ($route): string => $route->getActionName(),
            $this->registerDocuments(fn (PendingResourceRegistration $pending) => $pending->withSoftDeletes())->getRoutes(),
        );

        $this->assertSame(array_map(fn (string $method): string => DocumentController::class.'@'.$method, [
            'details', 'search', 'mutate', 'operate', 'destroy', 'restore', 'forceDestroy',
        ]), $actions);
    }

    #[Test]
    public function it_registers_both_soft_delete_endpoints_when_opted_in(): void
    {
        $table = $this->table($this->registerDocuments(fn (PendingResourceRegistration $pending) => $pending->withSoftDeletes()));

        $this->assertSame('POST documents/restore', $table['documents.restore']);
        $this->assertSame('DELETE documents/force', $table['documents.forceDestroy']);
        $this->assertCount(7, $table);
    }

    #[Test]
    public function it_registers_only_the_soft_delete_endpoint_asked_for(): void
    {
        $table = $this->table($this->registerDocuments(fn (PendingResourceRegistration $pending) => $pending->withSoftDeletes('restore')));

        $this->assertArrayHasKey('documents.restore', $table);
        $this->assertArrayNotHasKey('documents.forceDestroy', $table);
    }

    #[Test]
    public function it_prefixes_uris_and_names_with_the_enclosing_groups(): void
    {
        Route::prefix('api/v2')->name('api.v2.')->group(function (): void {
            Rest::resource('documents', DocumentController::class)->only('search');
        });

        $this->assertSame('api/v2/documents/search', Route::getRoutes()->getByName('api.v2.documents.search')?->uri());
    }

    #[Test]
    public function it_registers_when_the_pending_registration_is_released(): void
    {
        $before = $this->routeCount();

        $pending = Rest::resource('documents', DocumentController::class);
        $this->assertSame($before, $this->routeCount());

        unset($pending);
        $this->assertSame($before + 5, $this->routeCount());
    }

    /** @param Closure(PendingResourceRegistration): mixed $configure */
    #[Test]
    #[DataProvider('selections')]
    public function it_keeps_only_and_except_whatever_the_call_order(Closure $configure, array $expected): void
    {
        $this->assertSame($expected, array_keys($this->table($this->registerDocuments($configure))));
    }

    /** @return iterable<string, array{Closure(PendingResourceRegistration): mixed, list<string>}> */
    public static function selections(): iterable
    {
        $withoutDestroyAndOperate = ['documents.details', 'documents.search', 'documents.mutate', 'documents.restore'];

        yield 'except before soft deletes' => [
            fn (PendingResourceRegistration $pending) => $pending->except('destroy', 'operate')->withSoftDeletes('restore'),
            $withoutDestroyAndOperate,
        ];
        yield 'except after soft deletes' => [
            fn (PendingResourceRegistration $pending) => $pending->withSoftDeletes('restore')->except(['destroy', 'operate']),
            $withoutDestroyAndOperate,
        ];
        yield 'except a soft delete endpoint' => [
            fn (PendingResourceRegistration $pending) => $pending->withSoftDeletes('restore')->except('restore'),
            ['documents.details', 'documents.search', 'documents.mutate', 'documents.operate', 'documents.destroy'],
        ];
        yield 'only a soft delete endpoint without opting in' => [
            fn (PendingResourceRegistration $pending) => $pending->only('restore', 'search'),
            ['documents.search'],
        ];
        yield 'only with endpoint cases' => [
            fn (PendingResourceRegistration $pending) => $pending->withSoftDeletes()->only(Endpoint::Search, Endpoint::ForceDestroy),
            ['documents.search', 'documents.forceDestroy'],
        ];
    }
}
