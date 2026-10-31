<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle;

use Illuminate\Http\Request;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Fields\FieldPath;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\CountedPostController;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\CountedPostResource;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\EvaluatedDeclarations;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ResourceDefinitionTest extends PackageTestCase
{
    private const array DECLARATIONS = ['actions', 'fields', 'instructions', 'limits', 'relations', 'scopes', 'scoutFields', 'scoutInstructions'];

    private EvaluatedDeclarations $evaluated;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluated = $this->app->instance(EvaluatedDeclarations::class, new EvaluatedDeclarations);
        Rest::resource('counted-posts', CountedPostController::class)->register();
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Accounts, new Publishing];
    }

    #[Test]
    public function it_evaluates_each_declaration_once_however_often_the_resource_reads_it(): void
    {
        $context = $this->contextFor(User::factory()->staff()->make());

        $this->readEveryDeclaration($context->resource(CountedPostResource::class), 3);

        $this->assertSame(array_fill_keys(self::DECLARATIONS, 1), $this->evaluated->counts());
    }

    #[Test]
    public function it_evaluates_the_declarations_with_the_context_request_whatever_request_the_getters_receive(): void
    {
        $resource = $this->contextFor(User::factory()->staff()->make())->resource(CountedPostResource::class);
        $guestRequest = RestRequest::create('/counted-posts/search', 'POST');

        $this->readEveryDeclaration($resource, 2, $guestRequest);

        $this->assertSame(['id', 'title', 'views'], $resource->getFields($guestRequest)->names());
        $this->assertSame(array_fill_keys(self::DECLARATIONS, ['Grace']), array_map($this->evaluated->usersOf(...), array_combine(self::DECLARATIONS, self::DECLARATIONS)));
    }

    #[Test]
    public function it_evaluates_each_declaration_once_in_a_request(): void
    {
        $author = Author::factory()->create();
        Post::factory()->count(2)->for($author)->create(['featured' => true, 'views' => 250]);
        Post::factory()->for($author)->create(['featured' => false, 'views' => 250]);

        $response = $this->postJson('/counted-posts/actions/publish-posts', [
            'fields' => [['name' => 'notify', 'value' => false]],
            'search' => [
                'filters' => [['field' => 'author.id', 'value' => $author->id], ['field' => 'title', 'value' => 'Annual report']],
                'scopes' => [['name' => 'popular', 'parameters' => [100]]],
                'instructions' => [['name' => 'highlight-posts']],
                'sorts' => [['field' => 'title', 'direction' => 'asc']],
            ],
        ]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2]]);
        $this->assertSame(['actions' => 1, 'fields' => 1, 'instructions' => 1, 'limits' => 1, 'relations' => 1, 'scopes' => 1], $this->evaluated->counts());
    }

    #[Test]
    public function it_evaluates_the_declarations_again_for_the_next_request_and_its_user(): void
    {
        $author = Author::factory()->create();
        $post = Post::factory()->for($author)->create(['title' => 'Annual report', 'featured' => true, 'views' => 250]);
        $search = ['search' => [
            'filters' => [['field' => 'author.id', 'value' => $author->id]],
            'scopes' => [['name' => 'popular', 'parameters' => [100]]],
            'instructions' => [['name' => 'highlight-posts']],
            'limit' => 10,
        ]];

        $staffResponse = $this->actingAs(User::factory()->staff()->create())->postJson('/counted-posts/search', $search);
        $customerResponse = $this->actingAs(User::factory()->create())->postJson('/counted-posts/search', $search);

        $staffResponse->assertOk()->assertJsonPath('data', [['id' => $post->id, 'title' => 'Annual report', 'views' => 250]]);
        $customerResponse->assertOk()->assertJsonPath('data', [['id' => $post->id, 'title' => 'Annual report']]);
        $this->assertSame(['fields' => 2, 'instructions' => 2, 'limits' => 2, 'relations' => 2, 'scopes' => 2], $this->evaluated->counts());
        $this->assertSame(['Grace', 'Ada'], $this->evaluated->usersOf('fields'));
    }

    #[Test]
    public function it_evaluates_the_declarations_of_a_resource_created_directly_once(): void
    {
        $this->app->instance('request', Request::create('/counted-posts/search', 'POST'));
        $resource = new CountedPostResource;

        $this->readEveryDeclaration($resource, 2);

        $this->assertSame(array_fill_keys(self::DECLARATIONS, 1), $this->evaluated->counts());
        $this->assertSame([null], $this->evaluated->usersOf('fields'));
    }

    #[Test]
    public function it_serializes_a_resource_without_its_declarations(): void
    {
        $resource = $this->contextFor(User::factory()->staff()->make())->resource(CountedPostResource::class);
        $this->readEveryDeclaration($resource, 1);

        $this->assertSame(
            'O:68:"Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\CountedPostResource":2:{s:12:"defaultLimit";i:50;'
                ."s:34:\"\0Lomkit\Rest\Http\Resource\0context\";O:28:\"Lomkit\Rest\Http\RestContext\":0:{}}",
            serialize($resource),
        );
    }

    #[Test]
    public function it_keeps_no_declarations_on_the_resource_once_read(): void
    {
        $resource = $this->contextFor(User::factory()->staff()->make())->resource(CountedPostResource::class);

        $this->readEveryDeclaration($resource, 1);

        $this->assertSame(['defaultLimit', "\0Lomkit\Rest\Http\Resource\0context"], array_keys((array) $resource));
    }

    #[Test]
    public function it_evaluates_the_declarations_again_against_the_worker_request(): void
    {
        $resource = $this->contextFor(User::factory()->staff()->make())->resource(CountedPostResource::class);
        $this->readEveryDeclaration($resource, 1);
        $serialized = serialize($resource);

        $this->app->instance('request', Request::create('/'));
        $restored = unserialize($serialized);
        $request = $restored->context()->request;
        $declared = [...$restored->getActions($request), ...$restored->getInstructions($request), ...$restored->getScoutInstructions($request)];
        $bound = array_map(fn (Action|Instruction $declaration): Resource => $declaration->resource, $declared);
        $this->readEveryDeclaration($restored, 2);

        $this->assertSame(array_fill_keys(self::DECLARATIONS, 2), $this->evaluated->counts());
        $this->assertSame(['Grace', null], $this->evaluated->usersOf('fields'));
        $this->assertSame(['id', 'title'], $restored->getFields($request)->names());
        $this->assertSame(
            ['publish-posts', 'queued-publish-posts', 'batched-publish-posts', 'change-status', 'highlight-posts', 'in-status', 'highlight-posts', 'in-status'],
            array_map(fn (Action|Instruction $declaration): string => $declaration->uriKey(), $declared),
        );
        foreach ($bound as $boundResource) {
            $this->assertSame($restored, $boundResource);
        }
    }

    private function readEveryDeclaration(Resource $resource, int $times, ?RestRequest $request = null): void
    {
        $request ??= $resource->context()->request;

        for ($read = 0; $read < $times; $read++) {
            $resource->getFields($request);
            $resource->getScopes($request);
            $resource->getRelations($request);
            $resource->getInstructions($request);
            $resource->getActions($request);
            $resource->getLimits($request);
            $resource->getScoutFields($request);
            $resource->getScoutInstructions($request);
            $resource->relation('author');
            $resource->scope($request, 'popular');
            FieldPath::resolve($resource, 'title');
            $resource->actionExists($request, 'publish-posts');
            $resource->instruction($request, 'highlight-posts');
            $resource->scoutInstruction($request, 'highlight-posts');
            $resource->jsonSerialize();
        }
    }

    private function contextFor(?User $user): RestContext
    {
        $request = RestRequest::create('/counted-posts/search', 'POST');
        $request->setUserResolver(fn (): ?User => $user);

        return new RestContext($request, $this->app);
    }
}
