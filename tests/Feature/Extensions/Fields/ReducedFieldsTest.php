<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Fields;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MaskingFields;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadOnlyKeyCategoryController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadOnlyKeyProductController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReducedCategoryController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReducedProductController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReducedProductResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', [MaskingFields::class])]
class ReducedFieldsTest extends PackageTestCase
{
    use SeedsCatalog;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        MaskingFields::reset();
        MaskingFields::$narrow = static function (FieldsContext $context): void {
            if ($context->user !== null && $context->resource::class === ProductResource::class) {
                $context->readOnly('price');
                $context->unfilterable('price');
                $context->unsortable('price');
            }
        };
        Route::prefix('api')->group(function (): void {
            Rest::resource('reduced-products', ReducedProductController::class)->register();
            Rest::resource('reduced-categories', ReducedCategoryController::class)->register();
            Rest::resource('read-only-key-products', ReadOnlyKeyProductController::class)->register();
            Rest::resource('read-only-key-categories', ReadOnlyKeyCategoryController::class)->register();
        });
        $this->seedCatalog();
    }

    #[\Override]
    protected function tearDown(): void
    {
        MaskingFields::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_describes_narrowed_capabilities_like_declared_ones(): void
    {
        $narrowed = $this->actingAs(self::user())->getJson('/api/products?with=fields.capabilities,fields.filter,fields.rules,rules');
        $declared = $this->actingAs(self::user())->getJson('/api/reduced-products?with=fields.capabilities,fields.filter,fields.rules,rules');

        $narrowed->assertOk()->assertJsonPath('data.fields.3', [
            'name' => 'price',
            'label' => null,
            'type' => 'integer',
            'nullable' => false,
            'select' => true,
            'sort' => false,
            'filter' => ['operators' => []],
            'create' => false,
            'update' => false,
            'rules' => ['all' => ['integer'], 'create' => [], 'update' => []],
        ]);
        $this->assertSame($declared->json('data'), $narrowed->json('data'));
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('probes')]
    public function it_answers_like_a_declared_reduction(string $uri, string $twin, array $payload): void
    {
        $narrowed = $this->actingAs(self::user())->postJson($uri, $payload);
        $declared = $this->actingAs(self::user())->postJson($twin, $payload);

        $narrowed->assertUnprocessable();
        $this->assertSame($declared->json(), $narrowed->json());
    }

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function probes(): array
    {
        $products = ['/api/products/search', '/api/reduced-products/search'];
        $categories = ['/api/categories/search', '/api/reduced-categories/search'];
        $writes = ['/api/products/mutate', '/api/reduced-products/mutate'];

        return [
            'an update' => [...$writes, ['mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['price' => 10]]]]],
            'a create' => [...$writes, ['mutate' => [['operation' => 'create', 'attributes' => ['category_id' => 1, 'name' => 'Sofa', 'price' => 10]]]]],
            'a filter' => [...$products, ['search' => ['filters' => [['field' => 'price', 'value' => 39]]]]],
            'a filter through a relation' => [...$categories, ['search' => ['filters' => [['field' => 'products.price', 'value' => 39]]]]],
            'a sort' => [...$products, ['search' => ['sorts' => [['field' => 'price']]]]],
            'a max aggregate' => [...$categories, ['search' => ['aggregates' => [['relation' => 'products', 'type' => 'max', 'field' => 'price']]]]],
            'a sum aggregate' => [...$categories, ['search' => ['aggregates' => [['relation' => 'products', 'type' => 'sum', 'field' => 'price']]]]],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('relinks')]
    public function it_relinks_a_read_only_foreign_key_through_its_relation_like_a_declared_one(string $uri, string $twin, array $payload): void
    {
        MaskingFields::$narrow = static function (FieldsContext $context): void {
            if ($context->resource::class === ProductResource::class) {
                $context->readOnly('category_id');
            }
        };

        $narrowed = $this->actingAs(self::user())->postJson($uri, $payload);
        $narrowedKey = DB::table('products')->where('id', 1)->value('category_id');
        DB::table('products')->where('id', 1)->update(['category_id' => 1]);
        $declared = $this->actingAs(self::user())->postJson($twin, $payload);

        $narrowed->assertOk()->assertExactJson(['operations' => $payload['mutate']]);
        $this->assertSame(2, (int) $narrowedKey);
        $this->assertSame(2, (int) DB::table('products')->where('id', 1)->value('category_id'));
        $this->assertSame($declared->json(), $narrowed->json());
    }

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function relinks(): array
    {
        return [
            'a belongs to attach' => ['/api/products/mutate', '/api/read-only-key-products/mutate', ['mutate' => [
                ['operation' => 'update', 'key' => 1, 'relations' => ['category' => ['operation' => 'attach', 'key' => 2]]],
            ]]],
            'a has many attach' => ['/api/categories/mutate', '/api/read-only-key-categories/mutate', ['mutate' => [
                ['operation' => 'update', 'key' => 2, 'relations' => ['products' => [['operation' => 'attach', 'key' => 1]]]],
            ]]],
        ];
    }

    #[Test]
    public function it_refuses_a_read_only_foreign_key_as_an_attribute_like_a_declared_one(): void
    {
        MaskingFields::$narrow = static function (FieldsContext $context): void {
            if ($context->resource::class === ProductResource::class) {
                $context->readOnly('category_id');
            }
        };
        $payload = ['mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['category_id' => 2]]]];

        $narrowed = $this->actingAs(self::user())->postJson('/api/products/mutate', $payload);
        $declared = $this->actingAs(self::user())->postJson('/api/read-only-key-products/mutate', $payload);

        $narrowed->assertUnprocessable()->assertJsonPath('errors', ['mutate.0.attributes' => ['The category_id key of the mutate.0.attributes field is not writable on update.']]);
        $this->assertSame($declared->json(), $narrowed->json());
        $this->assertSame(1, (int) DB::table('products')->where('id', 1)->value('category_id'));
    }

    #[Test]
    public function it_keeps_each_reduction_apart(): void
    {
        MaskingFields::$narrow = static fn (FieldsContext $context): mixed => $context->readOnly('price');

        $search = $this->actingAs(self::user())->postJson('/api/products/search', ['search' => [
            'filters' => [['field' => 'price', 'operator' => '<', 'value' => 100]],
            'sorts' => [['field' => 'price']],
        ]]);
        $write = $this->actingAs(self::user())->postJson('/api/products/mutate', [
            'mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['price' => 10]]],
        ]);

        $search->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp', 'price' => 39],
            ['id' => 3, 'category_id' => 2, 'name' => 'Stool', 'price' => 45],
        ]);
        $write->assertUnprocessable()->assertJsonPath('errors', ['mutate.0.attributes' => ['The price key of the mutate.0.attributes field is not writable on update.']]);
    }

    #[Test]
    public function it_never_widens_a_declared_reduction(): void
    {
        MaskingFields::$narrow = static function (FieldsContext $context): void {
            if ($context->resource instanceof ReducedProductResource) {
                $context->readOnly('name');
            }
        };

        $response = $this->actingAs(self::user())->getJson('/api/reduced-products?with=fields.capabilities,fields.filter');

        $response->assertOk();
        $this->assertSame(
            [['price', true, false, [], false, false], ['name', true, true, ['=', '!=', 'like', 'not like', 'ilike', 'not ilike', 'in', 'not in'], false, false]],
            array_map(
                static fn (array $field): array => [$field['name'], $field['select'], $field['sort'], $field['filter']['operators'], $field['create'], $field['update']],
                [$response->json('data.fields.3'), $response->json('data.fields.2')],
            ),
        );
    }

    private static function user(): Authenticatable
    {
        return new GenericUser(['id' => 2]);
    }
}
