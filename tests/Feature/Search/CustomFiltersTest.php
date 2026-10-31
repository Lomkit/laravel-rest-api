<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\NamedAuthorController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\NamedAuthorPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class CustomFiltersTest extends PackageTestCase
{
    private const array ENDPOINTS = [
        'closure' => ['/api/authors', '/api/posts'],
        'filter object' => ['/named-authors', '/named-author-posts'],
    ];

    /** @var array<string, Author> */
    private array $authors = [];

    /** @var array<string, Post> */
    private array $posts = [];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('named-authors', NamedAuthorController::class)->register();
        Rest::resource('named-author-posts', NamedAuthorPostController::class)->register();

        foreach ([['Ada', 'Lovelace', 'Engines'], ['Alan', 'Turing', 'Machines'], ['Grace', 'Hopper', 'Compilers'], ['Hedy', 'Lamarr', 'Radio']] as [$first, $last, $title]) {
            $this->authors[$first] = Author::factory()->create(['first_name' => $first, 'last_name' => $last]);
            $this->posts[$title] = Post::factory()->create(['author_id' => $this->authors[$first]->id, 'title' => $title]);
        }
    }

    /** @return iterable<string, array{string, list<array<string, mixed>>, list<string>}> */
    public static function authorFilters(): iterable
    {
        $cases = [
            'after an and' => [[
                ['field' => 'last_name', 'operator' => '!=', 'value' => 'Hopper'],
                ['field' => 'full_name', 'operator' => 'like', 'value' => 'H%'],
            ], ['Hedy']],
            'after an or' => [[
                ['field' => 'first_name', 'value' => 'Ada'],
                ['field' => 'full_name', 'operator' => 'like', 'value' => 'H%', 'type' => 'or'],
            ], ['Ada', 'Grace', 'Hedy']],
            'in a nested group' => [[
                ['field' => 'last_name', 'operator' => '!=', 'value' => 'Lovelace'],
                ['nested' => [
                    ['field' => 'full_name', 'operator' => 'like', 'value' => 'A%'],
                    ['field' => 'full_name', 'value' => 'Hopper', 'type' => 'or'],
                ]],
            ], ['Alan', 'Grace']],
            'in a nested group after an or' => [[
                ['field' => 'first_name', 'value' => 'Ada'],
                ['type' => 'or', 'nested' => [
                    ['field' => 'full_name', 'operator' => 'like', 'value' => 'H%'],
                    ['field' => 'first_name', 'operator' => '!=', 'value' => 'Hedy'],
                ]],
            ], ['Ada', 'Grace']],
        ];

        foreach (self::ENDPOINTS as $variant => [$authors]) {
            foreach ($cases as $name => [$filters, $names]) {
                yield $variant.', '.$name => [$authors, $filters, $names];
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @param  list<string>  $names
     */
    #[Test]
    #[DataProvider('authorFilters')]
    public function it_keeps_a_custom_filter_inside_its_group(string $endpoint, array $filters, array $names): void
    {
        $response = $this->postJson($endpoint.'/search', ['search' => ['filters' => $filters, 'sorts' => [['field' => 'first_name']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', array_map(fn (string $name): int => $this->authors[$name]->id, $names));
    }

    /** @return iterable<string, array{string, list<array<string, mixed>>, list<string>}> */
    public static function postFilters(): iterable
    {
        $cases = [
            'through a relation' => [[
                ['field' => 'author.full_name', 'operator' => 'like', 'value' => 'H%'],
            ], ['Compilers', 'Radio']],
            'through a relation after an or' => [[
                ['field' => 'title', 'value' => 'Engines'],
                ['field' => 'author.full_name', 'value' => 'Turing', 'type' => 'or'],
            ], ['Engines', 'Machines']],
            'through a relation in a nested group' => [[
                ['field' => 'title', 'operator' => '!=', 'value' => 'Radio'],
                ['nested' => [
                    ['field' => 'author.full_name', 'operator' => 'like', 'value' => 'H%'],
                    ['field' => 'author.full_name', 'value' => 'Ada', 'type' => 'or'],
                ]],
            ], ['Engines', 'Compilers']],
        ];

        foreach (self::ENDPOINTS as $variant => [, $posts]) {
            foreach ($cases as $name => [$filters, $titles]) {
                yield $variant.', '.$name => [$posts, $filters, $titles];
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @param  list<string>  $titles
     */
    #[Test]
    #[DataProvider('postFilters')]
    public function it_runs_the_custom_filter_of_a_related_field(string $endpoint, array $filters, array $titles): void
    {
        $response = $this->postJson($endpoint.'/search', ['search' => ['filters' => $filters, 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', array_map(fn (string $title): int => $this->posts[$title]->id, $titles));
    }
}
