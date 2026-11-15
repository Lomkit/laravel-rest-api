<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Fields;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidFieldExtensionException;
use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Fields\FieldReduction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MaskingFields;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MaskingFieldsNamedB;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OtherMaskingFields;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OtherMaskingFieldsNamedA;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\PricelessProductController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RuledProductController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PricedCategoryController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FieldNarrowingRulesTest extends PackageTestCase
{
    use SeedsCatalog;

    private const array BOTH = [MaskingFields::class, OtherMaskingFields::class];

    private const array NAME_ORDER = [OtherMaskingFieldsNamedA::class, MaskingFieldsNamedB::class];

    private const array CLASS_ORDER = [MaskingFieldsNamedB::class, OtherMaskingFieldsNamedA::class];

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
        OtherMaskingFields::reset();
        config(['rest.extensions' => [MaskingFields::class]]);
        $this->seedCatalog();
    }

    #[\Override]
    protected function tearDown(): void
    {
        MaskingFields::reset();
        OtherMaskingFields::reset();

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('reductions')]
    public function it_refuses_an_unknown_field(string $method): void
    {
        MaskingFields::$narrow = static fn (FieldsContext $context): mixed => $context->{$method}('name', 'colour');

        $this->expectExceptionObject(new InvalidFieldExtensionException('The [mask] extension calls '.$method."('colour') on ".ProductResource::class.', whose fields() declares no field [colour]. Name a field that fields() returns; FieldsContext::declares() tells whether it does.'));

        $this->withoutExceptionHandling()->actingAs(self::user())->postJson('/api/products/search', ['search' => []]);
    }

    /** @return array<string, array{string}> */
    public static function reductions(): array
    {
        return [
            'hide' => ['hide'],
            'readOnly' => ['readOnly'],
            'unfilterable' => ['unfilterable'],
            'unsortable' => ['unsortable'],
        ];
    }

    /** @param list<class-string> $extensions */
    #[Test]
    #[DataProvider('orders')]
    public function it_names_the_extension_that_names_an_unknown_field_in_both_orders(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        MaskingFields::$narrow = static fn (FieldsContext $context): mixed => $context->hide('price');
        OtherMaskingFields::$narrow = static fn (FieldsContext $context): mixed => $context->unsortable('colour');

        $exception = $this->narrowingException();

        $this->assertTrue($exception->namesExtension('a'));
        $this->assertEquals(InvalidFieldExtensionException::unknownField('a', ProductResource::class, FieldReduction::Unsortable, 'colour'), $exception);
    }

    /** @param list<class-string> $extensions */
    #[Test]
    #[DataProvider('orders')]
    public function it_names_the_first_extension_in_name_order_when_both_are_wrong(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        MaskingFields::$narrow = static fn (FieldsContext $context): mixed => $context->hide('colour');
        OtherMaskingFields::$narrow = static fn (FieldsContext $context): mixed => $context->hide('size');

        $exception = $this->narrowingException();

        $this->assertTrue($exception->namesExtension('a'));
        $this->assertSame(InvalidFieldExtensionException::unknownField('a', ProductResource::class, FieldReduction::Hide, 'size')->getMessage(), $exception->getMessage());
    }

    /** @return array<string, array{list<class-string>}> */
    public static function orders(): array
    {
        return [
            'in name order' => [self::NAME_ORDER],
            'in class order' => [self::CLASS_ORDER],
        ];
    }

    #[Test]
    public function it_refuses_to_hide_the_model_key(): void
    {
        MaskingFields::$narrow = static fn (FieldsContext $context): mixed => $context->hide('id');

        $this->expectExceptionObject(new InvalidFieldExtensionException('The [mask] extension hides [id], the key of the model of '.ProductResource::class.': writes, actions and their responses name rows by it. Call readOnly(), unfilterable() or unsortable() on it instead.'));

        $this->withoutExceptionHandling()->actingAs(self::user())->postJson('/api/products/search', ['search' => []]);
    }

    #[Test]
    public function it_lets_the_key_be_read_only_unfilterable_and_unsortable(): void
    {
        MaskingFields::$narrow = static function (FieldsContext $context): void {
            $context->readOnly('id');
            $context->unfilterable('id');
            $context->unsortable('id');
        };

        $response = $this->actingAs(self::user())->getJson('/api/products?with=fields.capabilities,fields.filter');

        $response->assertOk()->assertJsonPath('data.fields.0', [
            'name' => 'id',
            'label' => null,
            'type' => 'id',
            'nullable' => false,
            'select' => true,
            'sort' => false,
            'filter' => ['operators' => []],
            'create' => false,
            'update' => false,
        ]);
    }

    /** @param list<class-string> $extensions */
    #[Test]
    #[DataProvider('orders')]
    public function it_gives_the_same_result_in_both_extension_orders(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        MaskingFields::$narrow = static fn (FieldsContext $context): mixed => $context->hide('price');
        OtherMaskingFields::$narrow = static function (FieldsContext $context): void {
            $context->readOnly('name');
            $context->unsortable('category_id');
        };

        $details = $this->actingAs(self::user())->getJson('/api/products?with=fields.capabilities,fields.filter');
        $write = $this->actingAs(self::user())->postJson('/api/products/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'attributes' => ['name' => 'Lamp', 'price' => 10]],
        ]]);

        $this->assertSame([
            ['id', true, true, false, false],
            ['category_id', true, false, true, true],
            ['name', true, true, false, false],
        ], array_map(
            static fn (array $field): array => [$field['name'], $field['select'], $field['sort'], $field['create'], $field['update']],
            $details->json('data.fields'),
        ));
        $write->assertUnprocessable()->assertExactJson([
            'message' => 'The name key of the mutate.0.attributes field is not writable on update. (and 1 more error)',
            'errors' => ['mutate.0.attributes' => [
                'The name key of the mutate.0.attributes field is not writable on update.',
                'The price key of the mutate.0.attributes field is not writable on update.',
            ]],
        ]);
    }

    #[Test]
    public function it_unions_the_reductions_of_two_extensions(): void
    {
        config(['rest.extensions' => self::BOTH]);
        MaskingFields::$narrow = static fn (FieldsContext $context): mixed => $context->hide('price', 'price');
        OtherMaskingFields::$narrow = static function (FieldsContext $context): void {
            $context->hide('price');
            $context->hide('name');
        };

        $response = $this->actingAs(self::user())->postJson('/api/products/search', ['search' => []]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 3, 'category_id' => 2],
            ['id' => 2, 'category_id' => 1],
            ['id' => 1, 'category_id' => 1],
        ]);
    }

    #[Test]
    public function it_tells_whether_the_resource_declares_a_field(): void
    {
        $answers = [];
        MaskingFields::$narrow = static function (FieldsContext $context) use (&$answers): void {
            $answers = [$context->declares('price'), $context->declares('colour')];
        };

        $this->actingAs(self::user())->postJson('/api/products/search', ['search' => []])->assertOk();

        $this->assertSame([true, false], $answers);
    }

    #[Test]
    #[DataProvider('reads')]
    public function it_refuses_an_extension_that_reads_the_fields_it_narrows(string $read): void
    {
        MaskingFields::$narrow = static fn (FieldsContext $context): mixed => $context->resource->definition()->{$read}();

        $this->expectExceptionObject(new InvalidFieldExtensionException('The [mask] extension reads the fields or the text search of '.ProductResource::class.' while the NarrowsFields extensions narrow them. Read the declared names with FieldsContext::declares() instead.'));

        $this->withoutExceptionHandling()->actingAs(self::user())->postJson('/api/products/search', ['search' => []]);
    }

    #[Test]
    public function it_names_the_extension_that_reads_the_fields_it_narrows(): void
    {
        config(['rest.extensions' => self::BOTH]);
        MaskingFields::$narrow = static fn (FieldsContext $context): mixed => $context->hide('price');
        OtherMaskingFields::$narrow = static fn (FieldsContext $context): mixed => $context->resource->definition()->fields();

        try {
            $this->withoutExceptionHandling()->actingAs(self::user())->postJson('/api/products/search', ['search' => []]);
        } catch (InvalidFieldExtensionException $exception) {
            $this->assertStringStartsWith('The [other] extension reads the fields or the text search of '.ProductResource::class, $exception->getMessage());
            $this->assertTrue($exception->namesExtension('other'));
            $this->assertFalse($exception->namesExtension('mask'));

            return;
        }

        $this->fail('The search passed although an extension read the fields it narrows.');
    }

    /** @return array<string, array{string}> */
    public static function reads(): array
    {
        return [
            'the fields' => ['fields'],
            'the text search' => ['text'],
        ];
    }

    #[Test]
    public function it_keeps_the_pivot_fields_of_a_relation(): void
    {
        Route::prefix('api')->group(function (): void {
            Rest::resource('priced-categories', PricedCategoryController::class)->register();
        });
        MaskingFields::$narrow = static function (FieldsContext $context): void {
            if ($context->resource instanceof ProductResource) {
                $context->hide('price');
            }
        };

        $attach = $this->actingAs(self::user())->postJson('/api/priced-categories/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 2, 'relations' => ['featuredProducts' => [['operation' => 'attach', 'key' => 3, 'pivot' => ['price' => 40]]]]],
        ]]);
        $search = $this->actingAs(self::user())->postJson('/api/priced-categories/search', ['search' => [
            'sorts' => [['field' => 'id']],
            'includes' => [['relation' => 'featuredProducts']],
        ]]);

        $attach->assertOk();
        $this->assertSame([['category_id' => 1, 'product_id' => 2, 'price' => 99], ['category_id' => 2, 'product_id' => 3, 'price' => 40]], array_map(
            static fn (object $row): array => (array) $row,
            DB::table('featured_products')->orderBy('product_id')->get(['category_id', 'product_id', 'price'])->all(),
        ));
        $search->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'name' => 'Lighting', 'featured_products' => [['id' => 2, 'category_id' => 1, 'name' => 'Floor lamp', 'pivot' => []]], '@more' => []],
            ['id' => 2, 'name' => 'Seating', 'featured_products' => [['id' => 3, 'category_id' => 2, 'name' => 'Stool', 'pivot' => []]], '@more' => []],
        ]);
    }

    #[Test]
    public function it_drops_the_resource_rules_of_a_hidden_field(): void
    {
        $this->registerRuledRoutes();
        MaskingFields::$narrow = static function (FieldsContext $context): void {
            if ($context->resource instanceof ProductResource) {
                $context->hide('price');
            }
        };

        foreach ([
            ['operation' => 'create', 'attributes' => ['category_id' => 1, 'name' => '']],
            ['operation' => 'update', 'key' => 1, 'attributes' => ['price' => 'x']],
        ] as $entry) {
            $ruled = $this->actingAs(self::user())->postJson('/api/ruled-products/mutate', ['mutate' => [$entry]]);
            $priceless = $this->actingAs(self::user())->postJson('/api/priceless-products/mutate', ['mutate' => [$entry]]);

            $ruled->assertUnprocessable();
            $this->assertSame($priceless->json(), $ruled->json());
        }

        $this->actingAs(self::user())->getJson('/api/ruled-products?with=rules')
            ->assertOk()
            ->assertJsonPath('data.rules', ['all' => [], 'create' => [], 'update' => []]);
    }

    #[Test]
    public function it_keeps_the_resource_rules_of_a_read_only_field(): void
    {
        $this->registerRuledRoutes();
        MaskingFields::$narrow = static fn (FieldsContext $context): mixed => $context->readOnly('price');

        $details = $this->actingAs(self::user())->getJson('/api/ruled-products?with=rules');
        $update = $this->actingAs(self::user())->postJson('/api/ruled-products/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'attributes' => ['price' => 'x']],
        ]]);

        $details->assertOk()->assertJsonPath('data.rules', ['all' => ['price' => ['integer']], 'create' => ['price' => ['required']], 'update' => []]);
        $update->assertUnprocessable()->assertExactJson([
            'message' => 'The mutate.0.attributes.price field must be an integer. (and 1 more error)',
            'errors' => [
                'mutate.0.attributes.price' => ['The mutate.0.attributes.price field must be an integer.'],
                'mutate.0.attributes' => ['The price key of the mutate.0.attributes field is not writable on update.'],
            ],
        ]);
    }

    private function registerRuledRoutes(): void
    {
        Route::prefix('api')->group(function (): void {
            Rest::resource('ruled-products', RuledProductController::class)->register();
            Rest::resource('priceless-products', PricelessProductController::class)->register();
        });
    }

    private function narrowingException(): InvalidFieldExtensionException
    {
        try {
            $this->withoutExceptionHandling()->actingAs(self::user())->postJson('/api/products/search', ['search' => []]);
        } catch (InvalidFieldExtensionException $exception) {
            return $exception;
        }

        $this->fail('The narrowing passed.');
    }

    private static function user(): Authenticatable
    {
        return new GenericUser(['id' => 2]);
    }
}
