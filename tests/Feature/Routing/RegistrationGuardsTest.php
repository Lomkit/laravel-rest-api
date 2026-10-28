<?php

namespace Lomkit\Rest\Tests\Feature\Routing;

use Closure;
use Lomkit\Rest\Exceptions\InvalidResourceRouteException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Routing\PendingResourceRegistration;
use Lomkit\Rest\Tests\Concerns\InspectsResourceRoutes;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentController;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RegistrationGuardsTest extends PackageTestCase
{
    use InspectsResourceRoutes;

    #[Test]
    public function it_rejects_a_dotted_name(): void
    {
        $this->assertRejected(
            fn () => Rest::resource('folders.documents', DocumentController::class),
            'The resource name [folders.documents] contains a dot. Register the child resource on its own and scope its query in the resource.',
        );
    }

    #[Test]
    public function it_rejects_a_slashed_name(): void
    {
        $this->assertRejected(
            fn () => Rest::resource('admin/documents', DocumentController::class),
            'The resource name [admin/documents] contains a slash. Wrap the call in Route::prefix() instead.',
        );
    }

    #[Test]
    #[DataProvider('malformedNames')]
    public function it_rejects_a_malformed_name(string $name): void
    {
        $this->assertRejected(
            fn () => Rest::resource($name, DocumentController::class),
            "The resource name [{$name}] must be one URI segment made of letters, digits, dashes and underscores.",
        );
    }

    /** @return iterable<string, array{string}> */
    public static function malformedNames(): iterable
    {
        yield 'empty' => [''];
        yield 'braces' => ['{documents}'];
        yield 'space' => ['my documents'];
        yield 'question mark' => ['a?b'];
        yield 'colon' => ['a:b'];
    }

    #[Test]
    public function it_accepts_letters_digits_dashes_and_underscores(): void
    {
        Rest::resource('model-with_default2', DocumentController::class)->only('search')->register();
        Rest::resource('catégories', DocumentController::class)->only('search')->register();

        $this->assertNotNull($this->app['router']->getRoutes()->getByName('catégories.search'));
        $this->assertNotNull($this->app['router']->getRoutes()->getByName('model-with_default2.search'));
    }

    #[Test]
    public function it_rejects_the_removed_options_argument(): void
    {
        $this->assertRejected(
            fn () => Rest::resource('documents', DocumentController::class, ['only' => ['search']]),
            'Rest::resource() no longer takes an options array for [documents]. Use only(), except(), names(), middleware() and withSoftDeletes() instead.',
        );
    }

    /** @param Closure(PendingResourceRegistration): mixed $change */
    #[Test]
    #[DataProvider('unknownKeyCalls')]
    public function it_rejects_an_unknown_endpoint_key(Closure $change): void
    {
        $this->assertRejected(
            fn () => $change(Rest::resource('documents', DocumentController::class)),
            'Unknown endpoint [force]. The endpoints are: details, search, mutate, operate, destroy, restore, forceDelete.',
        );
    }

    /** @return iterable<string, array{Closure(PendingResourceRegistration): mixed}> */
    public static function unknownKeyCalls(): iterable
    {
        yield 'only' => [fn (PendingResourceRegistration $pending) => $pending->only('force')];
        yield 'except' => [fn (PendingResourceRegistration $pending) => $pending->except('force')];
        yield 'withSoftDeletes' => [fn (PendingResourceRegistration $pending) => $pending->withSoftDeletes('force')];
        yield 'names' => [fn (PendingResourceRegistration $pending) => $pending->names(['force' => 'documents.purge'])];
        yield 'name' => [fn (PendingResourceRegistration $pending) => $pending->name('force', 'documents.purge')];
        yield 'middlewareFor' => [fn (PendingResourceRegistration $pending) => $pending->middlewareFor('force', 'auth')];
        yield 'withoutMiddlewareFor' => [fn (PendingResourceRegistration $pending) => $pending->withoutMiddlewareFor('force', 'auth')];
    }

    #[Test]
    public function it_rejects_an_endpoint_that_is_not_a_soft_delete_endpoint(): void
    {
        $this->assertRejected(
            fn () => Rest::resource('documents', DocumentController::class)->withSoftDeletes('search'),
            'The endpoint [search] is not a soft delete endpoint. withSoftDeletes() accepts: restore, forceDelete.',
        );
    }

    #[Test]
    public function it_rejects_an_empty_route_name(): void
    {
        $this->assertRejected(
            fn () => Rest::resource('documents', DocumentController::class)->name('search', ' '),
            'A route name cannot be empty.',
        );
    }

    private function assertRejected(Closure $registration, string $message): void
    {
        $before = $this->routeCount();

        try {
            $registration();
            $this->fail('The registration was not rejected.');
        } catch (InvalidResourceRouteException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }

        $this->assertSame($before, $this->routeCount());
    }
}
