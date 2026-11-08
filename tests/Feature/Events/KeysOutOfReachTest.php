<?php

namespace Lomkit\Rest\Tests\Feature\Events;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Events\KeysOutOfReach;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Events\Stubs\ReachBookController;
use Lomkit\Rest\Tests\Feature\Events\Stubs\ReachBookResource;
use Lomkit\Rest\Tests\Feature\Events\Stubs\ReachDocumentController;
use Lomkit\Rest\Tests\Feature\Events\Stubs\ReachDocumentResource;
use Lomkit\Rest\Tests\Feature\Mutate\Concerns\SeedsLibraryRows;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PerimeterLabelResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PerimeterShelfResource;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class KeysOutOfReachTest extends PackageTestCase
{
    use AssertsExactErrors;
    use SeedsLibraryRows;

    /** @var list<KeysOutOfReach> */
    private array $events = [];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library, new Archive];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('reach-books', ReachBookController::class)->register();
        Rest::resource('reach-documents', ReachDocumentController::class)
            ->withSoftDeletes()
            ->register();
        $this->seedLibrary();
        Label::query()->insert(['id' => 105, 'labelable_type' => Book::class, 'labelable_id' => 101, 'text' => 'Hidden']);
        Document::query()->insert([
            ['id' => 1, 'title' => 'Hidden', 'archived' => false, 'deleted_at' => null],
            ['id' => 2, 'title' => 'Annual report', 'archived' => false, 'deleted_at' => null],
            ['id' => 3, 'title' => 'Hidden', 'archived' => false, 'deleted_at' => '2026-01-01 00:00:00'],
            ['id' => 4, 'title' => 'Budget', 'archived' => false, 'deleted_at' => '2026-01-01 00:00:00'],
        ]);
        Event::listen(KeysOutOfReach::class, function (KeysOutOfReach $event): void {
            $this->events[] = $event;
        });
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $event
     */
    #[Test]
    #[DataProvider('refusals')]
    public function it_dispatches_one_event_for_the_keys_outside_the_perimeter(string $method, string $uri, array $body, string $error, array $event): void
    {
        $response = $this->json($method, $uri, $body);

        $this->assertExactErrors($response, [$error => ["The selected {$error} is invalid."]]);
        $this->assertSame([[...$event, 'capped' => false, 'guard' => 'web', 'user' => null]], $this->payloads());
    }

    /** @return array<string, array{string, string, array<string, mixed>, string, array<string, mixed>}> */
    public static function refusals(): array
    {
        $book = ['resource' => ReachBookResource::class, 'model' => Book::class, 'endpoint' => Endpoint::Mutate];
        $shelf = ['resource' => PerimeterShelfResource::class, 'model' => Shelf::class, 'endpoint' => Endpoint::Mutate];
        $label = ['resource' => PerimeterLabelResource::class, 'model' => Label::class, 'endpoint' => Endpoint::Mutate];
        $document = ['resource' => ReachDocumentResource::class, 'model' => Document::class];
        $shelves = fn (int $book, array $entry): array => ['mutate' => [['operation' => 'update', 'key' => $book, 'relations' => ['shelves' => [$entry]]]]];

        return [
            'a mutate update key' => ['POST', '/reach-books/mutate', ['mutate' => [['operation' => 'update', 'key' => 103, 'attributes' => ['title' => 'Persuasion']]]], 'mutate.0.key', [...$book, 'keys' => [103], 'paths' => ['mutate.0.key']]],
            'a mutate delete key' => ['POST', '/reach-books/mutate', ['mutate' => [['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'delete', 'key' => 105]]]]]], 'mutate.0.relations.labels.0.key', [...$label, 'keys' => [105], 'paths' => ['mutate.0.relations.labels.0.key']]],
            'a relation attach key' => ['POST', '/reach-books/mutate', $shelves(102, ['operation' => 'attach', 'key' => [103]]), 'mutate.0.relations.shelves.0.key', [...$shelf, 'keys' => [103], 'paths' => ['mutate.0.relations.shelves.0.key.0']]],
            'a relation detach key' => ['POST', '/reach-books/mutate', $shelves(101, ['operation' => 'detach', 'key' => [103]]), 'mutate.0.relations.shelves.0.key', [...$shelf, 'keys' => [103], 'paths' => ['mutate.0.relations.shelves.0.key.0']]],
            'a relation sync key' => ['POST', '/reach-books/mutate', $shelves(102, ['operation' => 'sync', 'key' => [102, 103]]), 'mutate.0.relations.shelves.0.key', [...$shelf, 'keys' => [103], 'paths' => ['mutate.0.relations.shelves.0.key.1']]],
            'a relation update key' => ['POST', '/reach-books/mutate', $shelves(101, ['operation' => 'update', 'key' => 103, 'attributes' => ['name' => 'Novels']]), 'mutate.0.relations.shelves.0.key', [...$shelf, 'keys' => [103], 'paths' => ['mutate.0.relations.shelves.0.key']]],
            'an action target' => ['POST', '/reach-documents/actions/archive-documents', ['resources' => [2, 1]], 'resources.1', [...$document, 'endpoint' => Endpoint::Operate, 'keys' => [1], 'paths' => ['resources.1']]],
            'a destroy key' => ['DELETE', '/reach-documents', ['resources' => [2, 1]], 'resources.1', [...$document, 'endpoint' => Endpoint::Destroy, 'keys' => [1], 'paths' => ['resources.1']]],
            'a restore key' => ['POST', '/reach-documents/restore', ['resources' => [3, 4]], 'resources.0', [...$document, 'endpoint' => Endpoint::Restore, 'keys' => [3], 'paths' => ['resources.0']]],
            'a force destroy key' => ['DELETE', '/reach-documents/force', ['resources' => [4, 3]], 'resources.1', [...$document, 'endpoint' => Endpoint::ForceDestroy, 'keys' => [3], 'paths' => ['resources.1']]],
        ];
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('reachable')]
    public function it_dispatches_no_event_for_keys_inside_the_perimeter(string $method, string $uri, array $body): void
    {
        $this->json($method, $uri, $body)->assertOk();

        $this->assertSame([], $this->events);
    }

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function reachable(): array
    {
        return [
            'mutate' => ['POST', '/reach-books/mutate', ['mutate' => [['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [['operation' => 'attach', 'key' => [102]]]]]]]],
            'operate' => ['POST', '/reach-documents/actions/archive-documents', ['resources' => [2]]],
            'destroy' => ['DELETE', '/reach-documents', ['resources' => [2]]],
            'restore' => ['POST', '/reach-documents/restore', ['resources' => [4]]],
            'force destroy' => ['DELETE', '/reach-documents/force', ['resources' => [4]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('otherRefusals')]
    public function it_dispatches_no_event_for_keys_refused_for_another_reason(string $method, string $uri, array $body, array $errors): void
    {
        $response = $this->json($method, $uri, $body);

        $this->assertExactErrors($response, $errors);
        $this->assertSame([], $this->events);
    }

    /** @return array<string, array{string, string, array<string, mixed>, array<string, list<string>>}> */
    public static function otherRefusals(): array
    {
        return [
            'a malformed destroy key' => ['DELETE', '/reach-documents', ['resources' => ['first']], ['resources.0' => ['The resources.0 field must be an integer.']]],
            'a malformed mutate key' => ['POST', '/reach-books/mutate', ['mutate' => [['operation' => 'update', 'key' => 'first']]], ['mutate.0.key' => ['The mutate.0.key field must be an integer.']]],
            'a malformed relation key' => ['POST', '/reach-books/mutate', ['mutate' => [['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [['operation' => 'attach', 'key' => ['first']]]]]]], ['mutate.0.relations.shelves.0.key.0' => ['The mutate.0.relations.shelves.0.key.0 field must be an integer.']]],
            'a repeated destroy key' => ['DELETE', '/reach-documents', ['resources' => [1, 1]], ['resources.0' => ['The resources.0 field has a duplicate value.'], 'resources.1' => ['The resources.1 field has a duplicate value.']]],
            'a reachable key with an invalid attribute' => ['POST', '/reach-books/mutate', ['mutate' => [['operation' => 'update', 'key' => 101, 'attributes' => ['title' => ['Dune']]]]], ['mutate.0.attributes.title' => ['The mutate.0.attributes.title field must be a string.']]],
        ];
    }

    #[Test]
    public function it_merges_the_paths_of_one_resource_into_one_event(): void
    {
        $response = $this->postJson('/reach-books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [['operation' => 'attach', 'key' => [103, 998]]]]],
            ['operation' => 'update', 'key' => 102, 'relations' => ['shelves' => [['operation' => 'attach', 'key' => [103]]]]],
        ]]);

        $this->assertExactErrors($response, [
            'mutate.0.relations.shelves.0.key' => ['The selected mutate.0.relations.shelves.0.key is invalid.'],
            'mutate.1.relations.shelves.0.key' => ['The selected mutate.1.relations.shelves.0.key is invalid.'],
        ]);
        $this->assertSame([[
            'resource' => PerimeterShelfResource::class,
            'model' => Shelf::class,
            'endpoint' => Endpoint::Mutate,
            'keys' => [103, 998],
            'paths' => ['mutate.0.relations.shelves.0.key.0', 'mutate.0.relations.shelves.0.key.1', 'mutate.1.relations.shelves.0.key.0'],
            'capped' => false,
            'guard' => 'web',
            'user' => null,
        ]], $this->payloads());
    }

    #[Test]
    public function it_dispatches_one_event_per_resource(): void
    {
        $response = $this->postJson('/reach-books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 103, 'attributes' => ['title' => 'Persuasion']],
            ['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [['operation' => 'attach', 'key' => [103]]]]],
        ]]);

        $this->assertExactErrors($response, [
            'mutate.0.key' => ['The selected mutate.0.key is invalid.'],
            'mutate.1.relations.shelves.0.key' => ['The selected mutate.1.relations.shelves.0.key is invalid.'],
        ]);
        $this->assertSame([
            ['resource' => ReachBookResource::class, 'model' => Book::class, 'endpoint' => Endpoint::Mutate, 'keys' => [103], 'paths' => ['mutate.0.key'], 'capped' => false, 'guard' => 'web', 'user' => null],
            ['resource' => PerimeterShelfResource::class, 'model' => Shelf::class, 'endpoint' => Endpoint::Mutate, 'keys' => [103], 'paths' => ['mutate.1.relations.shelves.0.key.0'], 'capped' => false, 'guard' => 'web', 'user' => null],
        ], $this->payloads());
    }

    #[Test]
    public function it_caps_the_keys_and_the_paths_at_fifty(): void
    {
        $keys = range(1001, 1060);

        $response = $this->deleteJson('/reach-documents', ['resources' => $keys]);

        $response->assertUnprocessable()->assertJsonCount(60, 'errors');
        $this->assertSame([[
            'resource' => ReachDocumentResource::class,
            'model' => Document::class,
            'endpoint' => Endpoint::Destroy,
            'keys' => range(1001, 1050),
            'paths' => array_map(fn (int $index): string => "resources.{$index}", range(0, 49)),
            'capped' => true,
            'guard' => 'web',
            'user' => null,
        ]], $this->payloads());
    }

    #[Test]
    public function it_names_the_user_and_the_guard(): void
    {
        $this->actingAs(new GenericUser(['id' => 7]));

        $this->deleteJson('/reach-documents', ['resources' => [1]])->assertUnprocessable();

        $this->assertCount(1, $this->events);
        $this->assertSame(['web', 7], [$this->events[0]->guard, $this->events[0]->user]);
    }

    #[Test]
    #[WithConfig('rest.precognition.enabled', true)]
    public function it_dispatches_the_event_on_a_precognitive_request_that_checks_the_keys(): void
    {
        $response = $this->precognitiveDestroy('resources');

        $this->assertExactErrors($response, ['resources.0' => ['The selected resources.0 is invalid.']]);
        $this->assertSame([['resource' => ReachDocumentResource::class, 'model' => Document::class, 'endpoint' => Endpoint::Destroy, 'keys' => [1], 'paths' => ['resources.0'], 'capped' => false, 'guard' => 'web', 'user' => null]], $this->payloads());
    }

    #[Test]
    #[WithConfig('rest.precognition.enabled', true)]
    public function it_dispatches_no_event_on_a_precognitive_request_that_leaves_the_keys_out(): void
    {
        $this->precognitiveDestroy('search')->assertNoContent();

        $this->assertSame([], $this->events);
    }

    #[Test]
    public function it_dispatches_an_event_that_serializes(): void
    {
        $this->actingAs(new GenericUser(['id' => 7]));
        $this->postJson('/reach-books/mutate', ['mutate' => [['operation' => 'update', 'key' => 103, 'attributes' => ['title' => 'Persuasion']]]])->assertUnprocessable();

        $this->assertCount(1, $this->events);
        $this->assertSame([
            'resource' => ReachBookResource::class,
            'model' => Book::class,
            'endpoint' => Endpoint::Mutate,
            'keys' => [103],
            'paths' => ['mutate.0.key'],
            'capped' => false,
            'guard' => 'web',
            'user' => 7,
        ], get_object_vars(unserialize(serialize($this->events[0]))));
    }

    /** @return list<array<string, mixed>> */
    private function payloads(): array
    {
        return array_map(get_object_vars(...), $this->events);
    }

    private function precognitiveDestroy(string $validateOnly): TestResponse
    {
        return $this->deleteJson('/reach-documents', ['resources' => [1]], [
            'Precognition' => 'true',
            'Precognition-Validate-Only' => $validateOnly,
        ]);
    }
}
