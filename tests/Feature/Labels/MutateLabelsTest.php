<?php

namespace Lomkit\Rest\Tests\Feature\Labels;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Labels\Stubs\LabelledCategoryController;
use Lomkit\Rest\Tests\Feature\Labels\Stubs\LabelledPostController;
use Lomkit\Rest\Tests\Feature\Labels\Stubs\LabelledProductController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MutateLabelsTest extends PackageTestCase
{
    use AssertsExactErrors;

    private const string LP = '/labelled-products/mutate';

    private const string LC = '/labelled-categories/mutate';

    private const string LM = '/labelled-posts/mutate';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog, new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('labelled-products', LabelledProductController::class)->register();
        Rest::resource('labelled-categories', LabelledCategoryController::class)->register();
        Rest::resource('labelled-posts', LabelledPostController::class)->register();
        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);
        Product::factory()->create(['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp', 'price' => 39]);
    }

    /** @param list<array<string, mixed>> $mutate */
    #[Test]
    #[DataProvider('labelledKeys')]
    public function it_names_a_key_by_the_label_of_its_field(string $uri, array $mutate, string $key, string $message): void
    {
        $response = $this->postJson($uri, ['mutate' => $mutate]);

        $this->assertExactErrors($response, [$key => [$message]]);
        $this->assertSame(1, Category::query()->count());
        $this->assertSame(1, Product::query()->count());
    }

    /** @return array<string, array{string, list<array<string, mixed>>, string, string}> */
    public static function labelledKeys(): array
    {
        $lamp = fn (mixed $price): array => [['operation' => 'create', 'attributes' => ['category_id' => 1, 'name' => 'Floor lamp', 'price' => $price]]];
        $seating = fn (mixed $price): array => [[
            'operation' => 'create',
            'attributes' => ['name' => 'Seating'],
            'relations' => ['products' => [['operation' => 'create', 'attributes' => ['name' => 'Floor lamp', 'price' => $price]]]],
        ]];
        $featured = fn (mixed $price): array => [[
            'operation' => 'update',
            'key' => 1,
            'relations' => ['featuredProducts' => [['operation' => 'attach', 'key' => 1, 'pivot' => ['price' => $price]]]],
        ]];
        $category = ['category' => ['operation' => 'create', 'attributes' => ['name' => 5]]];

        return [
            'a root attribute' => [self::LP, $lamp('x'), 'mutate.0.attributes.price', 'The unit price field must be an integer.'],
            'a field message' => [self::LP, $lamp(0), 'mutate.0.attributes.price', 'Give the unit price a positive value.'],
            'a field message on a type rule' => [
                self::LP,
                [['operation' => 'create', 'attributes' => ['category_id' => 1, 'name' => 5, 'price' => 10]]],
                'mutate.0.attributes.name',
                'The product name must be text.',
            ],
            'a field message on a rule object' => [
                self::LM,
                [['operation' => 'create', 'attributes' => ['title' => 'Annual report', 'status' => 'archived', 'published_on' => '2026-02-01']]],
                'mutate.0.attributes.status',
                'Pick a known post status.',
            ],
            'a to-many entry' => [self::LC, $seating('x'), 'mutate.0.relations.products.0.attributes.price', 'The unit price field must be an integer.'],
            'a field message in a to-many entry' => [self::LC, $seating(0), 'mutate.0.relations.products.0.attributes.price', 'Give the unit price a positive value.'],
            'a to-one entry' => [
                self::LP,
                [['operation' => 'create', 'attributes' => ['name' => 'Floor lamp', 'price' => 10], 'relations' => $category]],
                'mutate.0.relations.category.attributes.name',
                'The category name field must be a string.',
            ],
            'a pivot field' => [self::LC, $featured('x'), 'mutate.0.relations.featuredProducts.0.pivot.price', 'The featured price field must be an integer.'],
            'a field message on a pivot field' => [self::LC, $featured(0), 'mutate.0.relations.featuredProducts.0.pivot.price', 'Set the featured price above zero.'],
            'two levels down' => [
                self::LC,
                [[
                    'operation' => 'create',
                    'attributes' => ['name' => 'Seating'],
                    'relations' => ['products' => [['operation' => 'create', 'attributes' => ['name' => 'Floor lamp', 'price' => 10], 'relations' => $category]]],
                ]],
                'mutate.0.relations.products.0.relations.category.attributes.name',
                'The category name field must be a string.',
            ],
        ];
    }

    #[Test]
    public function it_names_every_entry_of_a_list_by_one_label(): void
    {
        $response = $this->postJson(self::LP, ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Floor lamp', 'price' => 'x']],
            ['operation' => 'create', 'attributes' => ['name' => 'Desk', 'price' => 'y']],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.price' => ['The unit price field must be an integer.'],
            'mutate.1.attributes.price' => ['The unit price field must be an integer.'],
        ]);
    }

    #[Test]
    public function it_names_the_attribute_that_a_rule_compares_with_by_its_label(): void
    {
        $response = $this->postJson(self::LM, ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['title' => 'Annual report', 'status' => 'draft', 'published_on' => '2026-02-01', 'published_at' => '2026-01-01 10:00:00'],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.published_at' => ['The publication time field must be a date after or equal to publication date.'],
        ]);
    }

    #[Test]
    #[WithConfig('rest.precognition.enabled', true)]
    public function it_names_a_key_by_its_label_in_a_precognitive_request(): void
    {
        $response = $this->postJson(self::LP, ['mutate' => [['operation' => 'create', 'attributes' => ['name' => 'Floor lamp', 'price' => 'x']]]], [
            'Precognition' => 'true',
            'Precognition-Validate-Only' => 'mutate.0.attributes.price',
        ]);

        $this->assertExactErrors($response, ['mutate.0.attributes.price' => ['The unit price field must be an integer.']]);
    }
}
