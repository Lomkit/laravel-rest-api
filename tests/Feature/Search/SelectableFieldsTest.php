<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\WriteOnlyAuthorController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SelectableFieldsTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_returns_the_selectable_fields_when_no_field_is_selected(): void
    {
        $ada = Author::factory()->create(['settings' => ['theme' => 'dark']]);

        $response = $this->postJson('/api/authors/search');

        $response->assertOk()->assertExactJson($this->page([
            ['id' => $ada->id, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'settings' => ['theme' => 'dark']],
        ]));
    }

    #[Test]
    public function it_returns_the_selectable_fields_of_an_included_resource(): void
    {
        $ada = Author::factory()->create();
        Post::factory()->for($ada)->create(['title' => 'Annual report']);

        $response = $this->postJson('/api/posts/search', [
            'search' => [
                'selects' => [['field' => 'title']],
                'includes' => [['relation' => 'author']],
            ],
        ]);

        $response->assertOk()->assertExactJson($this->page([
            ['title' => 'Annual report', 'author' => ['id' => $ada->id, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'settings' => null]],
        ]));
    }

    #[Test]
    public function it_returns_empty_rows_for_a_resource_without_selectable_fields(): void
    {
        Rest::resource('write-only-authors', WriteOnlyAuthorController::class)->register();
        Author::factory()->create();

        $response = $this->postJson('/write-only-authors/search');

        $response->assertOk()->assertExactJson($this->page([[]]));
    }

    #[Test]
    public function it_returns_only_the_selected_field(): void
    {
        $ada = Author::factory()->create();
        $alan = Author::factory()->create(['first_name' => 'Alan', 'last_name' => 'Turing']);

        $response = $this->postJson('/api/authors/search', [
            'search' => ['selects' => [['field' => 'id']]],
        ]);

        $response->assertOk()->assertExactJson($this->page([
            ['id' => $alan->id],
            ['id' => $ada->id],
        ]));
    }

    #[Test]
    public function it_returns_only_the_selected_fields(): void
    {
        $ada = Author::factory()->create();
        $alan = Author::factory()->create(['first_name' => 'Alan', 'last_name' => 'Turing']);

        $response = $this->postJson('/api/authors/search', [
            'search' => ['selects' => [['field' => 'last_name'], ['field' => 'id']]],
        ]);

        $response->assertOk()->assertExactJson($this->page([
            ['id' => $alan->id, 'last_name' => 'Turing'],
            ['id' => $ada->id, 'last_name' => 'Lovelace'],
        ]));
    }

    #[Test]
    #[DataProvider('unselectableFields')]
    public function it_rejects_a_field_that_cannot_be_selected(string $field): void
    {
        $response = $this->postJson('/api/authors/search', [
            'search' => ['selects' => [['field' => 'id'], ['field' => $field]]],
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.selects.1.field is invalid.',
            'errors' => [
                'search.selects.1.field' => ['The selected search.selects.1.field is invalid.'],
            ],
        ]);
    }

    /** @return array<string, array{string}> */
    public static function unselectableFields(): array
    {
        return [
            'a write only field' => ['api_token'],
            'a field that is not selectable' => ['created_at'],
            'a virtual field with a custom sort' => ['full_name'],
            'a virtual field' => ['initials'],
            'an undeclared field' => ['nickname'],
        ];
    }

    #[Test]
    public function it_rejects_a_field_that_cannot_be_selected_on_an_include(): void
    {
        $response = $this->postJson('/api/posts/search', [
            'search' => [
                'includes' => [['relation' => 'author', 'selects' => [['field' => 'api_token']]]],
            ],
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.includes.0.selects.0.field is invalid.',
            'errors' => [
                'search.includes.0.selects.0.field' => ['The selected search.includes.0.selects.0.field is invalid.'],
            ],
        ]);
    }

    #[Test]
    public function it_applies_the_selects_of_each_include_level(): void
    {
        $ada = Author::factory()->create();
        Author::factory()->create(['first_name' => 'Alan', 'last_name' => 'Turing']);
        Post::factory()->for($ada)->create(['title' => 'Annual report']);
        Post::factory()->for($ada)->create(['title' => 'Spring launch']);

        $response = $this->postJson('/api/authors/search', [
            'search' => [
                'selects' => [['field' => 'last_name']],
                'includes' => [[
                    'relation' => 'posts',
                    'selects' => [['field' => 'title']],
                    'includes' => [['relation' => 'author', 'selects' => [['field' => 'first_name']]]],
                ]],
            ],
        ]);

        $response->assertOk()->assertExactJson($this->page([
            ['last_name' => 'Turing', 'posts' => []],
            ['last_name' => 'Lovelace', 'posts' => [
                ['title' => 'Spring launch', 'author' => ['first_name' => 'Ada']],
                ['title' => 'Annual report', 'author' => ['first_name' => 'Ada']],
            ]],
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return array<string, mixed>
     */
    private function page(array $data): array
    {
        return [
            'current_page' => 1,
            'data' => $data,
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => count($data),
            'total' => count($data),
            'meta' => [],
        ];
    }
}
