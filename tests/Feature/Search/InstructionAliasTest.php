<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Search\InstructionNode;
use Lomkit\Rest\Tests\Feature\Search\Plan\ParsesSearchNodes;
use Lomkit\Rest\Tests\Feature\Search\Stubs\PopularPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class InstructionAliasTest extends PackageTestCase
{
    use ParsesSearchNodes;

    private const string TAKEN = 'field is required, because the key highlight-posts is already used.';

    private const string USED = 'is already used by another instruction.';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('popular-posts', PopularPostController::class)->register();
    }

    #[Test]
    public function it_applies_one_instruction_twice_under_two_keys(): void
    {
        $received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        Post::factory()->create(['id' => 1, 'title' => 'Featured hit', 'featured' => true, 'views' => 300]);
        Post::factory()->create(['id' => 2, 'title' => 'Featured mid', 'featured' => true, 'views' => 150]);
        Post::factory()->create(['id' => 3, 'title' => 'Hidden hit', 'featured' => false, 'views' => 300]);

        $response = $this->postJson('/api/posts/search', ['search' => [
            'instructions' => [
                ['name' => 'highlight-posts', 'fields' => [['name' => 'min_views', 'value' => 100]]],
                ['name' => 'highlight-posts', 'alias' => 'busy', 'fields' => [['name' => 'min_views', 'value' => 200]]],
            ],
            'selects' => [['field' => 'id'], ['field' => 'title']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 1, 'title' => 'Featured hit']]);
        $this->assertSame([
            ['handle', ['featured' => true, 'min_views' => 100]],
            ['handle', ['featured' => true, 'min_views' => 200]],
        ], $received->calls());
    }

    #[Test]
    public function it_keys_each_instruction_by_its_alias_or_else_its_name(): void
    {
        $resource = (new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app))->resource(PostResource::class);

        $node = $this->parse($resource, ['instructions' => [
            ['name' => 'highlight-posts'],
            ['name' => 'highlight-posts', 'alias' => 'busy'],
        ]]);

        $this->assertSame(
            [[null, 'highlight-posts'], ['busy', 'busy']],
            array_map(fn (InstructionNode $instruction): array => [$instruction->alias, $instruction->key()], $node->instructions),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $instructions
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('clashes')]
    public function it_refuses_an_instruction_key_that_cannot_name_the_instruction(string $path, array $instructions, array $errors): void
    {
        $response = $this->postJson($path, ['search' => ['instructions' => $instructions]]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{string, list<array<string, mixed>>, array<string, list<string>>}> */
    public static function clashes(): array
    {
        return [
            'the same instruction twice' => ['/api/posts/search', [['name' => 'highlight-posts'], ['name' => 'highlight-posts']], [
                'search.instructions.0.alias' => ['The search.instructions.0.alias '.self::TAKEN],
                'search.instructions.1.alias' => ['The search.instructions.1.alias '.self::TAKEN],
            ]],
            'an alias equal to the name of another entry' => ['/popular-posts/search', [['name' => 'popular'], ['name' => 'highlight-posts', 'alias' => 'popular']], [
                'search.instructions.0.alias' => ['The search.instructions.0.alias field is required, because the key popular is already used.'],
                'search.instructions.1.alias' => ['The search.instructions.1.alias '.self::USED],
            ]],
            'two equal aliases' => ['/api/posts/search', [
                ['name' => 'highlight-posts', 'alias' => 'busy'],
                ['name' => 'in-status', 'alias' => 'busy', 'fields' => [['name' => 'status', 'value' => 'review']]],
            ], [
                'search.instructions.0.alias' => ['The search.instructions.0.alias '.self::USED],
                'search.instructions.1.alias' => ['The search.instructions.1.alias '.self::USED],
            ]],
            'a null alias' => ['/api/posts/search', [['name' => 'highlight-posts', 'alias' => null]], [
                'search.instructions.0.alias' => ['The search.instructions.0.alias field must be a string.'],
            ]],
            'a dash' => ['/api/posts/search', [['name' => 'highlight-posts', 'alias' => 'highlight-posts']], [
                'search.instructions.0.alias' => ['The search.instructions.0.alias field format is invalid.'],
            ]],
            'more than 255 characters' => ['/api/posts/search', [['name' => 'highlight-posts', 'alias' => str_repeat('a', 256)]], [
                'search.instructions.0.alias' => ['The search.instructions.0.alias field must not be greater than 255 characters.'],
            ]],
        ];
    }

    #[Test]
    public function it_accepts_an_alias_equal_to_the_name_of_an_unrelated_key(): void
    {
        Post::factory()->create(['id' => 1, 'title' => 'Featured hit', 'featured' => true, 'views' => 300]);

        $response = $this->postJson('/popular-posts/search', ['search' => [
            'instructions' => [['name' => 'popular'], ['name' => 'highlight-posts', 'alias' => 'title']],
            'selects' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 1]]);
    }

    #[Test]
    public function it_refuses_the_same_instruction_twice_in_an_include(): void
    {
        Author::factory()->create(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'settings' => null]);

        $response = $this->postJson('/api/authors/search', ['search' => ['includes' => [
            ['relation' => 'posts', 'instructions' => [['name' => 'highlight-posts'], ['name' => 'highlight-posts']]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.includes.0.instructions.0.alias' => ['The search.includes.0.instructions.0.alias '.self::TAKEN],
            'search.includes.0.instructions.1.alias' => ['The search.includes.0.instructions.1.alias '.self::TAKEN],
        ]);
    }

    #[Test]
    public function it_refuses_the_same_instruction_twice_in_the_search_of_an_action(): void
    {
        $response = $this->postJson('/api/posts/actions/publish-posts', ['search' => [
            'instructions' => [['name' => 'highlight-posts'], ['name' => 'highlight-posts']],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.instructions.0.alias' => ['The search.instructions.0.alias '.self::TAKEN],
            'search.instructions.1.alias' => ['The search.instructions.1.alias '.self::TAKEN],
        ]);
    }

    #[Test]
    public function it_applies_aliased_instructions_in_an_include(): void
    {
        Author::factory()->create(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'settings' => null]);
        Post::factory()->create(['id' => 1, 'author_id' => 1, 'title' => 'Featured hit', 'featured' => true, 'views' => 300]);
        Post::factory()->create(['id' => 2, 'author_id' => 1, 'title' => 'Featured mid', 'featured' => true, 'views' => 150]);

        $response = $this->postJson('/api/authors/search', ['search' => [
            'selects' => [['field' => 'id']],
            'includes' => [[
                'relation' => 'posts',
                'selects' => [['field' => 'id']],
                'instructions' => [
                    ['name' => 'highlight-posts'],
                    ['name' => 'highlight-posts', 'alias' => 'busy', 'fields' => [['name' => 'min_views', 'value' => 200]]],
                ],
            ]],
        ]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 1, 'posts' => [['id' => 1]], '@more' => []]]);
    }
}
