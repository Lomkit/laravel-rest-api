<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Exceptions\MissingColumnException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\MetaAuthorController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\MetaPostController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\MetaPostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SearchMetaTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        MetaPostResource::$meta = [];
        MetaPostResource::$events = [];
        Rest::resource('meta-posts', MetaPostController::class)->register();
        Rest::resource('meta-authors', MetaAuthorController::class)->register();
        Post::factory()->create(['id' => 1, 'title' => 'Hit', 'featured' => true, 'views' => 300]);
        Post::factory()->create(['id' => 2, 'title' => 'Mid', 'featured' => true, 'views' => 150]);
        Post::factory()->create(['id' => 3, 'title' => 'Low', 'featured' => false, 'views' => 20]);
    }

    #[Test]
    public function it_adds_the_keys_of_search_meta_to_the_meta_of_the_page(): void
    {
        $response = $this->postJson('/meta-posts/search', ['search' => ['filters' => [['field' => 'views', 'operator' => '>=', 'value' => 100]]]]);

        $response->assertOk();
        $this->assertSame(['views' => 450, 'total' => 2, 'titled' => false], $response->json('meta'));
    }

    #[Test]
    public function it_passes_the_search_to_search_meta(): void
    {
        $response = $this->postJson('/meta-posts/search', ['search' => ['filters' => [['field' => 'title', 'value' => 'Hit']]]]);

        $response->assertOk();
        $this->assertSame(['views' => 300, 'total' => 1, 'titled' => true], $response->json('meta'));
    }

    #[Test]
    public function it_runs_search_meta_after_the_searched_event(): void
    {
        $this->postJson('/meta-posts/search')->assertOk();

        $this->assertSame(['searched', 'searchMeta'], MetaPostResource::$events);
    }

    #[Test]
    public function it_adds_the_keys_of_search_meta_after_the_class_level_gates(): void
    {
        $response = $this->postJson('/meta-posts/search', ['search' => ['gates' => ['create']]]);

        $response->assertOk();
        $this->assertSame('{"gates":{"authorized_to_create":{"allowed":true,"message":null}},"views":470,"total":3,"titled":false}', json_encode($response->json('meta')));
    }

    #[Test]
    public function it_refuses_a_search_meta_that_reads_a_column_that_the_page_did_not_load(): void
    {
        $response = $this->postJson('/meta-posts/search', ['search' => ['selects' => [['field' => 'title']]]]);

        $response->assertStatus(500);
        $this->assertInstanceOf(MissingColumnException::class, $response->exception);
        $this->assertSame(['searched', 'searchMeta'], MetaPostResource::$events);
    }

    /** @param array<string, mixed> $meta */
    #[Test]
    #[DataProvider('reservedKeys')]
    public function it_refuses_a_search_meta_key_that_the_package_fills(array $meta, string $key): void
    {
        MetaPostResource::$meta = $meta;

        $response = $this->postJson('/meta-posts/search');

        $response->assertStatus(500);
        $this->assertInstanceOf(InvalidResourceException::class, $response->exception);
        $this->assertSame('The resource ['.MetaPostResource::class."] returns the key [{$key}] from searchMeta(), which the package fills in the meta of a search page. Rename the key.", $response->exception->getMessage());
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function reservedKeys(): array
    {
        return [
            'the gates key' => [['gates' => []], 'gates'],
            'the instructions key' => [['instructions' => []], 'instructions'],
        ];
    }

    #[Test]
    #[WithConfig('rest.gates.key', 'abilities')]
    public function it_reserves_the_configured_gates_key(): void
    {
        MetaPostResource::$meta = ['abilities' => []];

        $response = $this->postJson('/meta-posts/search');

        $response->assertStatus(500);
        $this->assertSame('The resource ['.MetaPostResource::class.'] returns the key [abilities] from searchMeta(), which the package fills in the meta of a search page. Rename the key.', $response->exception?->getMessage());
    }

    #[Test]
    #[WithConfig('rest.gates.key', 'abilities')]
    public function it_accepts_a_gates_key_when_the_configuration_names_the_gates_otherwise(): void
    {
        MetaPostResource::$meta = ['gates' => 'kept'];

        $response = $this->postJson('/meta-posts/search', ['search' => ['gates' => ['create']]]);

        $response->assertOk();
        $this->assertSame('{"abilities":{"authorized_to_create":{"allowed":true,"message":null}},"gates":"kept","views":470,"total":3,"titled":false}', json_encode($response->json('meta')));
    }

    #[Test]
    public function it_adds_the_meta_of_each_applied_instruction_under_its_key(): void
    {
        $response = $this->postJson('/meta-posts/search', ['search' => ['instructions' => [
            ['name' => 'noted', 'fields' => [['name' => 'min_views', 'value' => 100]]],
            ['name' => 'noted', 'alias' => 'busy', 'fields' => [['name' => 'min_views', 'value' => 200]]],
            ['name' => 'popular'],
        ]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [1]);
        $this->assertSame([
            'views' => 300,
            'total' => 1,
            'titled' => false,
            'instructions' => [
                'noted' => ['notice' => 'Lists the posts with 100 views or more.'],
                'busy' => ['notice' => 'Lists the posts with 200 views or more.'],
            ],
        ], $response->json('meta'));
    }

    #[Test]
    public function it_leaves_out_the_instructions_that_add_no_meta(): void
    {
        $response = $this->postJson('/meta-posts/search', ['search' => ['instructions' => [['name' => 'noted'], ['name' => 'popular']]]]);

        $response->assertOk();
        $this->assertSame(['views' => 450, 'total' => 2, 'titled' => false], $response->json('meta'));
    }

    #[Test]
    public function it_renders_the_meta_outside_http_as_the_endpoint_does(): void
    {
        $search = ['instructions' => [['name' => 'noted', 'fields' => [['name' => 'min_views', 'value' => 100]]]], 'gates' => ['create']];
        $content = $this->postJson('/meta-posts/search', ['search' => $search])
            ->assertOk()
            ->getContent();

        $body = Rest::search(MetaPostResource::class)
            ->as(null)
            ->payload($search)
            ->render();

        $this->assertSame($content, json_encode($body));
        $this->assertSame(['noted' => ['notice' => 'Lists the posts with 100 views or more.']], json_decode($content, true)['meta']['instructions']);
    }

    #[Test]
    public function it_adds_no_meta_for_the_instructions_of_an_include(): void
    {
        Author::factory()->create(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'settings' => null]);
        Post::query()->whereKey([1, 2])->update(['author_id' => 1]);

        $response = $this->postJson('/meta-authors/search', ['search' => [
            'selects' => [['field' => 'id']],
            'includes' => [[
                'relation' => 'posts',
                'selects' => [['field' => 'id']],
                'instructions' => [['name' => 'noted', 'fields' => [['name' => 'min_views', 'value' => 100]]]],
            ]],
        ]]);

        $response->assertOk()->assertExactJson([
            'current_page' => 1,
            'data' => [['id' => 1, 'posts' => [['id' => 2], ['id' => 1]], '@more' => []]],
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => 1,
            'total' => 1,
            'meta' => [],
        ]);
    }
}
