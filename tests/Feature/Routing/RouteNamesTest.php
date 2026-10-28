<?php

namespace Lomkit\Rest\Tests\Feature\Routing;

use Lomkit\Rest\Exceptions\InvalidResourceRouteException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Routing\PendingResourceRegistration;
use Lomkit\Rest\Tests\Concerns\InspectsResourceRoutes;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentController;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RouteNamesTest extends PackageTestCase
{
    use InspectsResourceRoutes;

    #[Test]
    public function it_uses_a_string_given_to_names_as_prefix(): void
    {
        $table = $this->table($this->registerDocuments(fn (PendingResourceRegistration $pending) => $pending->only('details', 'search')->names('archive')));

        $this->assertSame(['archive.details', 'archive.search'], array_keys($table));
    }

    #[Test]
    public function it_uses_per_endpoint_names_exactly(): void
    {
        $table = $this->table($this->registerDocuments(fn (PendingResourceRegistration $pending) => $pending
            ->only('details', 'search', 'mutate')
            ->names(['search' => 'documents.find', 'details' => 'documents.describe'])));

        $this->assertSame(['documents.describe', 'documents.find', 'documents.mutate'], array_keys($table));
    }

    #[Test]
    public function it_combines_a_prefix_and_per_endpoint_names_in_any_order(): void
    {
        $prefixFirst = $this->registerDocuments(fn (PendingResourceRegistration $pending) => $pending
            ->only('details', 'search')->names('archive')->name('search', 'archive.find'));
        $nameFirst = Rest::resource('papers', DocumentController::class)
            ->only('details', 'search')->name('search', 'archive.find')->names('papers-archive');

        $this->assertSame(['archive.details', 'archive.find'], array_keys($this->table($prefixFirst)));
        $this->assertSame(['papers-archive.details', 'archive.find'], array_keys($this->table($nameFirst->register())));
    }

    #[Test]
    public function it_rejects_two_endpoints_of_one_resource_with_the_same_name(): void
    {
        $before = $this->routeCount();

        try {
            $this->registerDocuments(fn (PendingResourceRegistration $pending) => $pending
                ->name('search', 'documents.same')->name('mutate', 'documents.same'));
            $this->fail('The registration was not rejected.');
        } catch (InvalidResourceRouteException $exception) {
            $this->assertSame('Two endpoints of the resource use the route name [documents.same].', $exception->getMessage());
        }

        $this->assertSame($before, $this->routeCount());
    }
}
