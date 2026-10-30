<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle;

use Illuminate\Http\Request;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\ConstrainedPostController;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\ConstrainedPostResource;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\StaffProductResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithMigration('queue')]
class ResourceSerializationTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive, new Publishing];
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('queue.batching.database', $app['config']->get('database.default'));
    }

    /** @return iterable<string, array{string}> */
    public static function queuedActions(): iterable
    {
        yield 'queued' => ['queued-publish-posts'];
        yield 'batched' => ['batched-publish-posts'];
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
    #[DataProvider('queuedActions')]
    public function it_runs_a_queued_action_filtered_on_a_relation_whose_constraints_hold_callables(string $action): void
    {
        Rest::resource('constrained-posts', ConstrainedPostController::class)->register();
        $author = Author::factory()->create();
        Post::factory()->count(2)->for($author)->create();
        Post::factory()->create();

        $response = $this->postJson("/constrained-posts/actions/{$action}", [
            'search' => ['filters' => [['field' => 'author.id', 'value' => $author->id]]],
        ]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2]]);
        $this->assertSame(2, Post::query()->whereBelongsTo($author)->where('status', PostStatus::Published)->count());
        $this->assertSame(0, Post::query()->whereNull('author_id')->where('status', PostStatus::Published)->count());
    }

    #[Test]
    public function it_rebuilds_the_relations_after_a_serialization_round_trip(): void
    {
        $resource = (new RestContext(RestRequest::create('/constrained-posts/search', 'POST'), $this->app))->resource(ConstrainedPostResource::class);
        $resource->getRelations($resource->context()->request);

        $serialized = serialize($resource);
        $restored = unserialize($serialized);

        $this->assertStringNotContainsString('calculatedRelations', $serialized);
        $this->assertSame([
            'required_on_creation' => true,
            'prohibited_on_creation' => false,
            'required_on_update' => false,
            'prohibited_on_update' => false,
        ], $restored->relation('author')->jsonSerialize()['constraints']);
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
    public function it_rebuilds_the_scopes_after_a_serialization_round_trip(): void
    {
        $resource = (new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app))->resource(PostResource::class);
        $resource->getScopes($resource->context()->request);

        $serialized = serialize($resource);
        $restored = unserialize($serialized);

        $this->assertStringNotContainsString('calculatedScopes', $serialized);
        $this->assertSame(['publishedSince', 'popular', 'viewedBetween', 'featured', 'reaching'], array_map(fn (Scope $scope): string => $scope->name, $restored->getScopes($restored->context()->request)));
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
