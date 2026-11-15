<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Fields;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Extensions\Applicability;
use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MaskingFields;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\PolicedProductController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\PriceCapPolicy;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\PricelessCategoryController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\PricelessProductController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\PricelessProductResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', ['mask' => MaskingFields::class])]
class HiddenFieldsTest extends PackageTestCase
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

        Applicability::flush();
        MaskingFields::reset();
        MaskingFields::$narrow = static function (FieldsContext $context): void {
            if ($context->user !== null && $context->resource instanceof ProductResource) {
                $context->hide('price');
            }
        };
        Route::prefix('api')->group(function (): void {
            Rest::resource('priceless-products', PricelessProductController::class)->register();
            Rest::resource('priceless-categories', PricelessCategoryController::class)->register();
            Rest::resource('policed-products', PolicedProductController::class)->register();
        });
        $this->seedCatalog();
    }

    #[\Override]
    protected function tearDown(): void
    {
        MaskingFields::reset();
        Applicability::flush();

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('columnSelections')]
    public function it_renders_no_hidden_field_for_the_narrowed_user(bool $selectColumns): void
    {
        config(['rest.search.select_columns' => $selectColumns]);

        $user = $this->actingAs(self::user())->postJson('/api/products/search', ['search' => []]);
        $guest = $this->actingAsGuest()->postJson('/api/products/search', ['search' => []]);

        $user->assertOk()->assertJsonPath('data', self::PRICELESS_PRODUCTS);
        $guest->assertOk()->assertJsonPath('data', self::PRODUCTS);
    }

    /** @return array<string, array{bool}> */
    public static function columnSelections(): array
    {
        return [
            'selecting the columns' => [true],
            'selecting every column' => [false],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('probes')]
    public function it_answers_a_hidden_field_like_an_undeclared_one(string $uri, string $twin, array $payload, int $status): void
    {
        $hidden = $this->actingAs(self::user())->postJson($uri, $payload);
        $undeclared = $this->actingAs(self::user())->postJson($twin, $payload);

        $hidden->assertStatus($status);
        $this->assertSame([$undeclared->status(), $undeclared->json()], [$hidden->status(), $hidden->json()]);
    }

    /** @return array<string, array{string, string, array<string, mixed>, int}> */
    public static function probes(): array
    {
        $products = ['/api/products/search', '/api/priceless-products/search'];
        $categories = ['/api/categories/search', '/api/priceless-categories/search'];
        $productWrites = ['/api/products/mutate', '/api/priceless-products/mutate'];
        $categoryWrites = ['/api/categories/mutate', '/api/priceless-categories/mutate'];
        $price = ['field' => 'price', 'value' => 39];

        return [
            'a filter' => [...$products, ['search' => ['filters' => [$price]]], 422],
            'a filter with an operator' => [...$products, ['search' => ['filters' => [['field' => 'price', 'operator' => '>', 'value' => 40]]]], 422],
            'a nested filter' => [...$products, ['search' => ['filters' => [['nested' => [$price]]]]], 422],
            'an or filter beside a valid one' => [...$products, ['search' => ['filters' => [['field' => 'name', 'value' => 'Stool'], ['field' => 'price', 'type' => 'or', 'value' => 39]]]], 422],
            'a filter through a relation' => [...$categories, ['search' => ['filters' => [['field' => 'products.price', 'value' => 39]]]], 422],
            'a filter through a pivot relation' => [...$categories, ['search' => ['filters' => [['field' => 'featuredProducts.price', 'value' => 39]]]], 422],
            'a relation group filter' => [...$categories, ['search' => ['filters' => [['relation' => 'products', 'filters' => [$price]]]]], 422],
            'a sort' => [...$products, ['search' => ['sorts' => [['field' => 'price']]]], 422],
            'a select' => [...$products, ['search' => ['selects' => [['field' => 'price']]]], 422],
            'a select in an include' => [...$categories, ['search' => ['includes' => [['relation' => 'products', 'selects' => [['field' => 'price']]]]]], 422],
            'a filter in an include' => [...$categories, ['search' => ['includes' => [['relation' => 'products', 'filters' => [$price]]]]], 422],
            'a sort in an include' => [...$categories, ['search' => ['includes' => [['relation' => 'products', 'sorts' => [['field' => 'price']]]]]], 422],
            'a sum aggregate' => [...$categories, ['search' => ['aggregates' => [['relation' => 'products', 'type' => 'sum', 'field' => 'price']]]], 422],
            'a max aggregate' => [...$categories, ['search' => ['aggregates' => [['relation' => 'products', 'type' => 'max', 'field' => 'price']]]], 422],
            'a filter in a count aggregate' => [...$categories, ['search' => ['aggregates' => [['relation' => 'products', 'type' => 'count', 'filters' => [$price]]]]], 422],
            'an include alias named as the field' => [...$products, ['search' => ['sorts' => [['field' => 'id']], 'includes' => [['relation' => 'category', 'alias' => 'price']]]], 200],
            'an attribute of an update' => [...$productWrites, ['mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['price' => 10]]]], 422],
            'an attribute of a create' => [...$productWrites, ['mutate' => [['operation' => 'create', 'attributes' => ['category_id' => 1, 'name' => 'Sofa', 'price' => 10]]]], 422],
            'an attribute of a nested create' => [...$categoryWrites, ['mutate' => [['operation' => 'update', 'key' => 1, 'relations' => ['products' => [
                ['operation' => 'create', 'attributes' => ['name' => 'Sofa', 'price' => 10]],
            ]]]]], 422],
            'a select of the written records' => [...$productWrites, ['mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['name' => 'Lamp']]], 'response' => ['selects' => [['field' => 'price']]]], 422],
        ];
    }

    #[Test]
    #[DataProvider('relatedProbes')]
    public function it_answers_a_hidden_related_field_like_an_unknown_name(string $uri, string $hidden, string $unknown): void
    {
        MaskingFields::$narrow = static function (FieldsContext $context): void {
            if ($context->user !== null && $context->resource::class === CategoryResource::class) {
                $context->hide('name');
            }
        };

        $narrowed = $this->actingAs(self::user())->postJson($uri, ['search' => json_decode($hidden, true)]);
        $undeclared = $this->actingAs(self::user())->postJson($uri, ['search' => json_decode($unknown, true)]);

        $narrowed->assertUnprocessable();
        $this->assertSame(
            str_replace('colour', 'name', (string) $undeclared->getContent()),
            $narrowed->getContent(),
        );
    }

    /** @return array<string, array{string, string, string}> */
    public static function relatedProbes(): array
    {
        $paths = static fn (string $uri, string $search): array => [$uri, sprintf($search, 'name'), sprintf($search, 'colour')];

        return [
            'a relation sort' => $paths('/api/products/search', '{"sorts":[{"field":"category.%s"}]}'),
            'a relation filter' => $paths('/api/products/search', '{"filters":[{"field":"category.%s","value":"Lighting"}]}'),
            'a select in an include' => $paths('/api/products/search', '{"includes":[{"relation":"category","selects":[{"field":"%s"}]}]}'),
            'a filter two relations away' => $paths('/api/categories/search', '{"filters":[{"field":"products.category.%s","value":"Lighting"}]}'),
            'a select two includes away' => $paths('/api/categories/search', '{"includes":[{"relation":"products","includes":[{"relation":"category","selects":[{"field":"%s"}]}]}]}'),
        ];
    }

    #[Test]
    public function it_renders_includes_like_the_twin(): void
    {
        $search = ['search' => ['sorts' => [['field' => 'id']], 'includes' => [
            ['relation' => 'products', 'sorts' => [['field' => 'id']], 'includes' => [['relation' => 'category']]],
            ['relation' => 'featuredProducts', 'sorts' => [['field' => 'id']]],
        ]]];

        $narrowed = $this->actingAs(self::user())->postJson('/api/categories/search', $search);
        $twin = $this->actingAs(self::user())->postJson('/api/priceless-categories/search', $search);

        $narrowed->assertOk();
        $this->assertSame($twin->json('data'), $narrowed->json('data'));
        $this->assertSame([
            ['id' => 2, 'category_id' => 1, 'name' => 'Floor lamp', 'pivot' => []],
        ], $narrowed->json('data.0.featured_products'));
    }

    #[Test]
    public function it_lists_the_fields_of_the_twin_in_details(): void
    {
        $narrowed = $this->actingAs(self::user())->getJson('/api/products?with=fields.capabilities,fields.filter,fields.rules,rules');
        $twin = $this->actingAs(self::user())->getJson('/api/priceless-products?with=fields.capabilities,fields.filter,fields.rules,rules');

        $narrowed->assertOk();
        $this->assertSame($twin->json('data'), $narrowed->json('data'));
        $this->assertSame(['id', 'category_id', 'name'], array_column($narrowed->json('data.fields'), 'name'));
    }

    #[Test]
    public function it_renders_written_records_without_the_hidden_field(): void
    {
        $response = $this->actingAs(self::user())->postJson('/api/products/mutate', [
            'mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['name' => 'Desk light']]],
            'response' => [],
        ]);

        $response->assertOk()->assertExactJson([
            'operations' => [['operation' => 'update', 'key' => 1]],
            'records' => [['id' => 1, 'category_id' => 1, 'name' => 'Desk light']],
        ]);
    }

    #[Test]
    public function it_hides_the_field_in_rest_search_for_the_given_user(): void
    {
        $user = Rest::search(ProductResource::class)->as(self::user())->payload([])->render();
        $guest = Rest::search(ProductResource::class)->as(null)->payload([])->render();

        $this->assertSame(self::PRICELESS_PRODUCTS, $user['data']);
        $this->assertSame(self::PRODUCTS, $guest['data']);
        $this->assertSame(
            $this->validationErrors(PricelessProductResource::class),
            $this->validationErrors(ProductResource::class),
        );
    }

    #[Test]
    public function it_narrows_once_per_request(): void
    {
        $this->actingAs(self::user())->postJson('/api/categories/search', ['search' => [
            'filters' => [['field' => 'products.name', 'value' => 'Stool']],
            'includes' => [['relation' => 'products', 'includes' => [['relation' => 'category']]], ['relation' => 'featuredProducts']],
            'aggregates' => [['relation' => 'products', 'type' => 'count']],
        ]])->assertOk();

        $this->assertSame(
            [CategoryResource::class, ProductResource::class],
            array_map(static fn (FieldsContext $context): string => $context->resource::class, MaskingFields::$contexts),
        );
    }

    #[Test]
    public function it_narrows_for_each_user_of_consecutive_requests(): void
    {
        MaskingFields::$narrow = static function (FieldsContext $context): void {
            if ($context->user?->getAuthIdentifier() === 2) {
                $context->hide('price');
            }
        };

        $first = $this->actingAs(self::user())->postJson('/api/products/search', ['search' => []]);
        $second = $this->actingAsGuest()->postJson('/api/products/search', ['search' => []]);
        $third = $this->actingAs(new GenericUser(['id' => 3]))->postJson('/api/products/search', ['search' => []]);

        $first->assertOk()->assertJsonPath('data', self::PRICELESS_PRODUCTS);
        $second->assertOk()->assertJsonPath('data', self::PRODUCTS);
        $third->assertOk()->assertJsonPath('data', self::PRODUCTS);
    }

    #[Test]
    public function it_passes_the_resource_and_the_acting_user(): void
    {
        $user = self::user();

        $this->actingAs($user)->postJson('/api/products/search', ['search' => []])->assertOk();
        $this->actingAsGuest()->postJson('/api/products/search', ['search' => []])->assertOk();

        $this->assertCount(2, MaskingFields::$contexts);
        $this->assertInstanceOf(ProductResource::class, MaskingFields::$contexts[0]->resource);
        $this->assertSame($user, MaskingFields::$contexts[0]->user);
        $this->assertNull(MaskingFields::$contexts[1]->user);
    }

    #[Test]
    public function it_loads_a_hidden_column_that_the_resource_always_selects(): void
    {
        Gate::policy(Product::class, PriceCapPolicy::class);

        $response = $this->actingAs(self::user())->postJson('/api/policed-products/search', ['search' => ['gates' => ['view']]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 3, 'category_id' => 2, 'name' => 'Stool', 'gates' => ['authorized_to_view' => ['allowed' => true, 'message' => null]]],
            ['id' => 2, 'category_id' => 1, 'name' => 'Floor lamp', 'gates' => ['authorized_to_view' => ['allowed' => false, 'message' => null]]],
            ['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp', 'gates' => ['authorized_to_view' => ['allowed' => true, 'message' => null]]],
        ]);
    }

    #[Test]
    public function it_narrows_once_for_a_mutation_and_before_its_transaction(): void
    {
        $levels = [];
        MaskingFields::$narrow = static function (FieldsContext $context) use (&$levels): void {
            $levels[] = DB::transactionLevel();
            $context->hide('price');
        };
        $outside = DB::transactionLevel();

        $response = $this->actingAs(self::user())->postJson('/api/products/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => 1, 'attributes' => ['name' => 'Desk light']],
                ['operation' => 'update', 'key' => 3, 'attributes' => ['name' => 'Bar stool']],
            ],
            'response' => [],
        ]);

        $response->assertOk()->assertJsonPath('records', [
            ['id' => 1, 'category_id' => 1, 'name' => 'Desk light'],
            ['id' => 3, 'category_id' => 2, 'name' => 'Bar stool'],
        ]);
        $this->assertSame([$outside], $levels);
    }

    /**
     * @param  class-string<ProductResource|PricelessProductResource>  $resource
     * @return array<string, list<string>>
     */
    private function validationErrors(string $resource): array
    {
        try {
            Rest::search($resource)->as(self::user())->payload(['filters' => [['field' => 'price', 'value' => 39]]])->render();
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        $this->fail('The search of '.$resource.' passed validation.');
    }

    private static function user(): Authenticatable
    {
        return new GenericUser(['id' => 2]);
    }
}
