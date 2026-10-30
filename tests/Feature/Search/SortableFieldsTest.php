<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\HeadlinedAuthorController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SortableFieldsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    /** @param list<array{string, string}> $names */
    #[Test]
    #[DataProvider('fullNameOrders')]
    public function it_orders_by_the_custom_sort_of_a_field(string $direction, array $names): void
    {
        Author::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        Author::factory()->create(['first_name' => 'Anne', 'last_name' => 'Hopper']);
        Author::factory()->create(['first_name' => 'Alan', 'last_name' => 'Turing']);
        Author::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);

        $response = $this->searchAuthors([['field' => 'full_name', 'direction' => $direction]]);

        $response->assertOk()->assertExactJson($this->page(array_map(
            fn (array $name): array => ['first_name' => $name[0], 'last_name' => $name[1]],
            $names,
        )));
    }

    /** @return array<string, array{string, list<array{string, string}>}> */
    public static function fullNameOrders(): array
    {
        return [
            'ascending' => ['asc', [['Anne', 'Hopper'], ['Grace', 'Hopper'], ['Ada', 'Lovelace'], ['Alan', 'Turing']]],
            'descending' => ['desc', [['Alan', 'Turing'], ['Ada', 'Lovelace'], ['Grace', 'Hopper'], ['Anne', 'Hopper']]],
        ];
    }

    #[Test]
    #[DataProvider('unsortableFields')]
    public function it_rejects_a_field_that_cannot_be_sorted(string $field): void
    {
        $response = $this->searchAuthors([['field' => 'last_name'], ['field' => $field]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.sorts.1.field is invalid.',
            'errors' => [
                'search.sorts.1.field' => ['The selected search.sorts.1.field is invalid.'],
            ],
        ]);
    }

    /** @return array<string, array{string}> */
    public static function unsortableFields(): array
    {
        return [
            'a json field' => ['settings'],
            'a field that is not sortable' => ['id'],
            'a write only field' => ['api_token'],
            'a virtual field without a custom sort' => ['initials'],
            'an undeclared field' => ['nickname'],
        ];
    }

    #[Test]
    public function it_sorts_on_a_field_that_cannot_be_selected(): void
    {
        Author::factory()->create(['first_name' => 'Ada', 'created_at' => '2026-01-03 09:00:00']);
        Author::factory()->create(['first_name' => 'Alan', 'created_at' => '2026-01-01 09:00:00']);
        Author::factory()->create(['first_name' => 'Grace', 'created_at' => '2026-01-02 09:00:00']);

        $response = $this->postJson('/api/authors/search', [
            'search' => ['sorts' => [['field' => 'created_at']], 'selects' => [['field' => 'first_name']]],
        ]);

        $response->assertOk()->assertExactJson($this->page([
            ['first_name' => 'Alan'],
            ['first_name' => 'Grace'],
            ['first_name' => 'Ada'],
        ]));
    }

    #[Test]
    public function it_rejects_a_field_that_cannot_be_sorted_on_an_include(): void
    {
        $response = $this->postJson('/api/posts/search', [
            'search' => [
                'sorts' => [['field' => 'id']],
                'includes' => [['relation' => 'author', 'sorts' => [['field' => 'id']]]],
            ],
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected search.includes.0.sorts.0.field is invalid.',
            'errors' => [
                'search.includes.0.sorts.0.field' => ['The selected search.includes.0.sorts.0.field is invalid.'],
            ],
        ]);
    }

    /** @param list<string> $titles */
    #[Test]
    #[DataProvider('headlineOrders')]
    public function it_orders_an_include_by_the_custom_sort_of_a_field(string $direction, array $titles): void
    {
        Rest::resource('headlined-authors', HeadlinedAuthorController::class)->register();
        $ada = Author::factory()->create();
        Post::factory()->for($ada)->create(['title' => 'Notes']);
        Post::factory()->for($ada)->create(['title' => 'Analytical engine']);
        Post::factory()->for($ada)->create(['title' => 'Bernoulli numbers']);

        $response = $this->postJson('/headlined-authors/search', [
            'search' => [
                'selects' => [['field' => 'first_name']],
                'includes' => [['relation' => 'posts', 'sorts' => [['field' => 'headline', 'direction' => $direction]], 'selects' => [['field' => 'title']]]],
            ],
        ]);

        $response->assertOk()->assertExactJson($this->page([
            ['first_name' => 'Ada', 'posts' => array_map(fn (string $title): array => ['title' => $title], $titles)],
        ]));
    }

    /** @return array<string, array{string, list<string>}> */
    public static function headlineOrders(): array
    {
        return [
            'ascending' => ['asc', ['Analytical engine', 'Bernoulli numbers', 'Notes']],
            'descending' => ['desc', ['Notes', 'Bernoulli numbers', 'Analytical engine']],
        ];
    }

    #[Test]
    public function it_orders_an_include_by_the_qualified_column_when_its_pivot_has_the_same_column(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        $deskLamp = Product::factory()->for($lighting)->create(['name' => 'Desk lamp', 'price' => 39]);
        $floorLamp = Product::factory()->for($lighting)->create(['name' => 'Floor lamp', 'price' => 129]);
        $lighting->featuredProducts()->attach([$deskLamp->id => ['price' => 99], $floorLamp->id => ['price' => 35]]);
        DB::enableQueryLog();

        $response = $this->postJson('/api/categories/search', [
            'search' => [
                'selects' => [['field' => 'name']],
                'includes' => [['relation' => 'featuredProducts', 'sorts' => [['field' => 'price']], 'selects' => [['field' => 'name']]]],
            ],
        ]);

        $response->assertOk()->assertExactJson($this->page([
            ['name' => 'Lighting', 'featured_products' => [['name' => 'Desk lamp', 'pivot' => []], ['name' => 'Floor lamp', 'pivot' => []]]],
        ]));
        $joined = array_values(array_filter(
            array_map(fn (array $query): string => str_replace(['"', '`'], '', $query['query']), DB::getQueryLog()),
            fn (string $query): bool => str_contains($query, 'inner join featured_products'),
        ));
        $this->assertCount(1, $joined);
        $this->assertStringContainsString(' order by products.price asc', $joined[0]);
    }

    /** @param list<array<string, string>> $sorts */
    private function searchAuthors(array $sorts): TestResponse
    {
        return $this->postJson('/api/authors/search', [
            'search' => ['sorts' => $sorts, 'selects' => [['field' => 'first_name'], ['field' => 'last_name']]],
        ]);
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
