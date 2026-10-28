<?php

namespace Lomkit\Rest\Tests\Feature\Routing;

use Illuminate\Routing\ResourceRegistrar as IlluminateResourceRegistrar;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidResourceRouteException;
use Lomkit\Rest\Http\Routing\PendingResourceRegistration;
use Lomkit\Rest\Tests\Concerns\InspectsResourceRoutes;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

class ResourceVerbsTest extends PackageTestCase
{
    use InspectsResourceRoutes;

    /** @var array<string, string> */
    private array $laravelVerbs = [];

    #[Before]
    public function rememberLaravelVerbs(): void
    {
        $this->laravelVerbs = IlluminateResourceRegistrar::verbs();
    }

    #[After]
    public function restoreLaravelVerbs(): void
    {
        (new ReflectionProperty(IlluminateResourceRegistrar::class, 'verbs'))->setValue(null, $this->laravelVerbs);
    }

    #[Test]
    public function it_uses_the_verbs_set_with_route_resource_verbs(): void
    {
        Route::resourceVerbs(['search' => 'find', 'mutate' => 'write', 'actions' => 'run', 'restore' => 'undelete', 'forceDestroy' => 'purge']);

        $this->assertSame([
            'documents.details' => 'GET|HEAD documents',
            'documents.search' => 'POST documents/find',
            'documents.mutate' => 'POST documents/write',
            'documents.operate' => 'POST documents/run/{action}',
            'documents.destroy' => 'DELETE documents',
            'documents.restore' => 'POST documents/undelete',
            'documents.forceDestroy' => 'DELETE documents/purge',
        ], $this->table($this->registerDocuments(fn (PendingResourceRegistration $pending) => $pending->withSoftDeletes())));
    }

    #[Test]
    public function it_keeps_the_package_verbs_for_keys_left_unset(): void
    {
        Route::resourceVerbs(['search' => 'find']);

        $table = $this->table($this->registerDocuments());

        $this->assertSame('POST documents/find', $table['documents.search']);
        $this->assertSame('POST documents/mutate', $table['documents.mutate']);
    }

    #[Test]
    public function it_rejects_a_verb_that_is_not_one_segment(): void
    {
        Route::resourceVerbs(['search' => 'find/all']);

        $this->expectExceptionObject(InvalidResourceRouteException::invalidVerb('search'));

        $this->registerDocuments();
    }

    #[Test]
    public function it_rejects_verbs_that_put_two_endpoints_on_one_route(): void
    {
        Route::resourceVerbs(['restore' => 'search']);
        $before = $this->routeCount();

        try {
            $this->registerDocuments(fn (PendingResourceRegistration $pending) => $pending->withSoftDeletes());
            $this->fail('The registration was not rejected.');
        } catch (InvalidResourceRouteException $exception) {
            $this->assertSame('Two endpoints of the resource use the route [POST documents/search].', $exception->getMessage());
        }

        $this->assertSame($before, $this->routeCount());
    }
}
