<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PricedCategoryController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class UnknownMutationKeysTest extends PackageTestCase
{
    use AssertsExactErrors;

    private const array SEATING = ['name' => 'Seating'];

    private const array FLOOR_LAMP = ['name' => 'Floor lamp', 'price' => 129];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog, new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('priced-categories', PricedCategoryController::class)->register();
    }

    /** @param list<array<string, mixed>> $mutate */
    #[Test]
    #[DataProvider('unknownKeys')]
    public function it_rejects_an_unknown_key_on_its_key(string $uri, array $mutate, string $key): void
    {
        $message = 'The '.$key.' field is prohibited.';

        $response = $this->postJson($uri, ['mutate' => $mutate]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{string, list<array<string, mixed>>, string}> */
    public static function unknownKeys(): array
    {
        return [
            'in a root entry' => ['/api/categories/mutate', [
                ['operation' => 'create', 'attributes' => self::SEATING, 'atributes' => []],
            ], 'mutate.0.atributes'],
            'named with an underscore' => ['/api/categories/mutate', [
                ['operation' => 'create', 'attributes' => self::SEATING, 'sort_by' => 'name'],
            ], 'mutate.0.sort_by'],
            'in a to-many entry' => ['/api/categories/mutate', [['operation' => 'create', 'attributes' => self::SEATING, 'relations' => ['products' => [
                ['operation' => 'create', 'attributes' => self::FLOOR_LAMP, 'atributes' => []],
            ]]]], 'mutate.0.relations.products.0.atributes'],
            'in a to-one entry' => ['/api/products/mutate', [['operation' => 'create', 'attributes' => self::FLOOR_LAMP, 'relations' => [
                'category' => ['operation' => 'create', 'attributes' => self::SEATING, 'atributes' => []],
            ]]], 'mutate.0.relations.category.atributes'],
            'pivot at the root' => ['/api/categories/mutate', [
                ['operation' => 'create', 'attributes' => self::SEATING, 'pivot' => []],
            ], 'mutate.0.pivot'],
            'pivot in a one-to-many entry' => ['/api/categories/mutate', [['operation' => 'create', 'attributes' => self::SEATING, 'relations' => ['products' => [
                ['operation' => 'create', 'attributes' => self::FLOOR_LAMP, 'pivot' => []],
            ]]]], 'mutate.0.relations.products.0.pivot'],
            'a type outside a morph to entry' => ['/api/products/mutate', [['operation' => 'create', 'attributes' => self::FLOOR_LAMP, 'relations' => [
                'category' => ['operation' => 'create', 'type' => 'categories', 'attributes' => self::SEATING],
            ]]], 'mutate.0.relations.category.type'],
        ];
    }

    #[Test]
    public function it_rejects_a_key_that_holds_a_wildcard_on_its_entry(): void
    {
        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => self::SEATING, '*' => 1],
        ]]);

        $this->assertExactErrors($response, ['mutate.0' => ['The * key is not valid for the mutate.0 field.']]);
    }

    #[Test]
    public function it_reports_the_unknown_keys_of_an_entry_first(): void
    {
        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 5], 'atributes' => []],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.atributes' => ['The mutate.0.atributes field is prohibited.'],
            'mutate.0.attributes.name' => ['The mutate.0.attributes.name field must be a string.'],
        ]);
    }

    #[Test]
    public function it_rejects_an_unknown_relation_on_the_relations_key(): void
    {
        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => self::SEATING, 'relations' => ['suppliers' => []]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations' => ['The suppliers key is not valid for the mutate.0.relations field.']]);
    }

    #[Test]
    public function it_names_the_relations_of_a_to_one_entry_as_laravel_does(): void
    {
        $response = $this->postJson('/api/books/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['title' => 'Emma'],
            'relations' => ['latestLoan' => [
                'operation' => 'create',
                'attributes' => ['borrower' => 'Grace'],
                'relations' => ['suppliers' => []],
            ]],
        ]]]);

        $this->assertExactErrors($response, [
            'mutate.0.relations.latestLoan.relations' => ['The suppliers key is not valid for the mutate.0.relations.latest loan.relations field.'],
        ]);
    }

    #[Test]
    public function it_accepts_the_pivot_of_a_many_to_many_entry(): void
    {
        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);
        Product::factory()->create(['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp']);

        $response = $this->postJson('/priced-categories/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 1,
            'relations' => ['featuredProducts' => [['operation' => 'attach', 'key' => 1, 'pivot' => ['price' => 10]]]],
        ]]]);

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [1]]);
        $this->assertDatabaseCount('featured_products', 1);
        $this->assertDatabaseHas('featured_products', ['category_id' => 1, 'product_id' => 1, 'price' => 10]);
    }
}
