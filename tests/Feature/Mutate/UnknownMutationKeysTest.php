<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PricedCategoryController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\TracedCategoryController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\TracedCategoryResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class UnknownMutationKeysTest extends PackageTestCase
{
    use CapturesQueries;

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

        TracedCategoryResource::$entries = [];
        Rest::resource('priced-categories', PricedCategoryController::class)->register();
        Rest::resource('traced-categories', TracedCategoryController::class)->register();
    }

    /**
     * @param  list<array<string, mixed>>  $mutate
     * @param  list<array<string, mixed>>  $operations
     * @param  array<string, list<string>>  $names
     */
    #[Test]
    #[DataProvider('unknownKeys')]
    public function it_writes_an_entry_that_holds_an_unknown_key_as_if_the_key_were_absent(string $uri, array $mutate, array $operations, array $names): void
    {
        $response = $this->postJson($uri, ['mutate' => $mutate]);

        $response->assertOk()->assertExactJson(['operations' => $operations]);
        $this->assertSame($names, [
            'categories' => Category::query()->orderBy('id')->pluck('name')->all(),
            'products' => Product::query()->orderBy('id')->pluck('name')->all(),
        ]);
    }

    /** @return array<string, array{string, list<array<string, mixed>>, list<array<string, mixed>>, array<string, list<string>>}> */
    public static function unknownKeys(): array
    {
        $created = ['operation' => 'create', 'key' => 1];

        return [
            'in a root entry' => ['/api/categories/mutate', [
                ['operation' => 'create', 'attributes' => self::SEATING, 'atributes' => ['name' => 'Lighting']],
            ], [$created], ['categories' => ['Seating'], 'products' => []]],
            'named with an underscore' => ['/api/categories/mutate', [
                ['operation' => 'create', 'attributes' => self::SEATING, 'sort_by' => 'name'],
            ], [$created], ['categories' => ['Seating'], 'products' => []]],
            'holding a wildcard, a dot or a final backslash' => ['/api/categories/mutate', [
                ['operation' => 'create', 'attributes' => self::SEATING, '*' => 1, 'a.b' => 2, 'a\\' => 3],
            ], [$created], ['categories' => ['Seating'], 'products' => []]],
            'in a to-many entry' => ['/api/categories/mutate', [['operation' => 'create', 'attributes' => self::SEATING, 'relations' => ['products' => [
                ['operation' => 'create', 'attributes' => self::FLOOR_LAMP, 'atributes' => ['name' => 'Desk lamp']],
            ]]]], [[...$created, 'relations' => ['products' => [$created]]]], ['categories' => ['Seating'], 'products' => ['Floor lamp']]],
            'in a to-one entry' => ['/api/products/mutate', [['operation' => 'create', 'attributes' => self::FLOOR_LAMP, 'relations' => [
                'category' => ['operation' => 'create', 'attributes' => self::SEATING, 'atributes' => ['name' => 'Lighting']],
            ]]], [[...$created, 'relations' => ['category' => $created]]], ['categories' => ['Seating'], 'products' => ['Floor lamp']]],
            'a relation that is not declared' => ['/api/categories/mutate', [
                ['operation' => 'create', 'attributes' => self::SEATING, 'relations' => ['suppliers' => [['operation' => 'create', 'attributes' => self::FLOOR_LAMP]], 'brand' => 5]],
            ], [$created], ['categories' => ['Seating'], 'products' => []]],
            'a relation that is not declared in a to-one entry' => ['/api/products/mutate', [['operation' => 'create', 'attributes' => self::FLOOR_LAMP, 'relations' => [
                'category' => ['operation' => 'create', 'attributes' => self::SEATING, 'relations' => ['suppliers' => []]],
            ]]], [[...$created, 'relations' => ['category' => $created]]], ['categories' => ['Seating'], 'products' => ['Floor lamp']]],
        ];
    }

    #[Test]
    public function it_keeps_an_undeclared_attribute_and_pivot_key_away_from_the_model_and_the_hooks(): void
    {
        $this->travelTo('2026-11-08 10:00:00');
        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);
        Product::factory()->create(['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp']);

        $response = $this->postJson('/traced-categories/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['name' => 'Seating', 'created_at' => '2020-01-01 00:00:00', 'stock' => 3],
            'relations' => ['featuredProducts' => [['operation' => 'attach', 'key' => 1, 'pivot' => ['price' => 10, 'discount' => 5]]]],
        ]]]);

        $response->assertOk()->assertExactJson(['operations' => [
            ['operation' => 'create', 'key' => 2, 'relations' => ['featuredProducts' => [['operation' => 'attach', 'key' => 1]]]],
        ]]);
        $this->assertSame([[['name' => 'Seating'], [['price' => 10]]]], TracedCategoryResource::$entries);
        $this->assertSame(['2026-11-08 10:00:00'], Category::query()->whereKey(2)->pluck('created_at')->map(fn (mixed $date): string => (string) $date)->all());
        $this->assertDatabaseCount('featured_products', 1);
        $this->assertDatabaseHas('featured_products', ['category_id' => 2, 'product_id' => 1, 'price' => 10]);
    }

    #[Test]
    public function it_runs_no_update_for_an_entry_whose_attributes_are_all_unknown(): void
    {
        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);
        $this->captureQueries();

        $response = $this->postJson('/traced-categories/mutate', ['mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['nme' => 'Seating']]]]);

        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'update', 'key' => 1]]]);
        $this->assertSame([[[], []]], TracedCategoryResource::$entries);
        $this->assertSame([], array_values(array_filter($this->statements, fn (array $statement): bool => ! str_starts_with($statement[0], 'select'))));
        $this->assertSame(['Lighting'], Category::query()->pluck('name')->all());
    }

    /** @param list<array<string, mixed>> $mutate */
    #[Test]
    #[DataProvider('misplacedKeys')]
    public function it_still_refuses_a_known_key_outside_its_entry(string $uri, array $mutate, string $key): void
    {
        $message = 'The '.$key.' field is prohibited.';

        $response = $this->postJson($uri, ['mutate' => $mutate]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{string, list<array<string, mixed>>, string}> */
    public static function misplacedKeys(): array
    {
        return [
            'pivot at the root' => ['/api/categories/mutate', [
                ['operation' => 'create', 'attributes' => self::SEATING, 'pivot' => [], 'atributes' => []],
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
    public function it_accepts_the_pivot_of_a_many_to_many_entry(): void
    {
        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);
        Product::factory()->create(['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp']);

        $response = $this->postJson('/priced-categories/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 1,
            'relations' => ['featuredProducts' => [['operation' => 'attach', 'key' => 1, 'pivot' => ['price' => 10]]]],
        ]]]);

        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'update', 'key' => 1, 'relations' => ['featuredProducts' => [['operation' => 'attach', 'key' => 1]]]]]]);
        $this->assertDatabaseCount('featured_products', 1);
        $this->assertDatabaseHas('featured_products', ['category_id' => 1, 'product_id' => 1, 'price' => 10]);
    }
}
