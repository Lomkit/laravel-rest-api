<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle;

use Illuminate\Http\Request;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\StaffProductResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ResourceSerializationTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[Test]
    public function it_runs_a_queued_action_on_a_resource_whose_fields_hold_closures(): void
    {
        Document::factory()->count(2)->create();

        $response = $this->postJson('/api/documents/actions/queued-archive-documents', [
            'search' => ['sorts' => [['field' => 'title', 'direction' => 'asc']]],
        ]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2]]);
        $this->assertSame(2, Document::query()->where('archived', true)->count());
    }

    #[Test]
    public function it_rebuilds_the_fields_after_a_serialization_round_trip(): void
    {
        $resource = $this->documentResource();
        $fields = $resource->getFields($resource->context()->request);

        $restored = unserialize(serialize($resource));

        $this->assertNotSame($fields, $restored->getFields($restored->context()->request));
        $this->assertSame(['id', 'title', 'archived'], $restored->getFields($restored->context()->request)->names());
    }

    #[Test]
    public function it_keeps_the_resource_properties_through_serialization(): void
    {
        $resource = $this->documentResource();
        $resource->defaultLimit = 25;
        $resource->getFields($resource->context()->request);

        $this->assertSame(25, unserialize(serialize($resource))->defaultLimit);
    }

    #[Test]
    public function it_rebuilds_the_fields_against_the_worker_request(): void
    {
        $request = RestRequest::create('/api/staff-products/search', 'POST');
        $request->setUserResolver(fn (): User => User::factory()->staff()->make());
        $resource = (new RestContext($request, $this->app))->resource(StaffProductResource::class);
        $resource->getFields($request);
        $serialized = serialize($resource);

        $this->app->instance('request', Request::create('/'));
        $restored = unserialize($serialized);

        $this->assertSame(['id', 'name'], $restored->getFields($restored->context()->request)->names());
    }

    private function documentResource(): Resource
    {
        return (new RestContext(RestRequest::create('/api/documents/search', 'POST'), $this->app))
            ->resource(DocumentResource::class);
    }
}
