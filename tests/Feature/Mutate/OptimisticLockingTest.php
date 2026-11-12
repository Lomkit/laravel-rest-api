<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\Event;
use Lomkit\Rest\Events\ResourceMutated;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\TimestamplessProductResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\UndeclaredVersionProductResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\VersionedCategoryController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\VersionedProductController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\VersionedProductResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class OptimisticLockingTest extends PackageTestCase
{
    private const string NOW = '2026-03-01T10:00:00.000000Z';

    private Category $lighting;

    private Product $desk;

    private Product $floor;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-03-01 10:00:00');
        Rest::resource('versioned-products', VersionedProductController::class)->register();
        Rest::resource('versioned-categories', VersionedCategoryController::class)->register();
        VersionedProductResource::$hooks = [];
        $this->lighting = Category::factory()->create(['name' => 'Lighting', 'updated_at' => '2026-01-01 07:00:00']);
        $this->desk = $this->product('Desk lamp', '2026-01-01 08:00:00');
        $this->floor = $this->product('Floor lamp', '2026-01-02 08:00:00');
    }

    #[Test]
    public function it_writes_an_update_that_sends_the_version_of_its_row(): void
    {
        $response = $this->postJson('/versioned-products/mutate', [
            'mutate' => [['operation' => 'update', 'key' => $this->desk->id, 'version' => '2026-01-01T08:00:00.000000Z', 'attributes' => ['name' => 'Desk light']]],
            'response' => ['selects' => [['field' => 'updated_at']]],
        ]);

        $response->assertOk()->assertExactJson([
            'operations' => [['operation' => 'update', 'key' => $this->desk->id]],
            'records' => [['updated_at' => self::NOW]],
        ]);
        $this->assertSame(['Desk light', 'Floor lamp'], $this->names());
    }

    #[Test]
    public function it_writes_nothing_when_one_entry_sends_another_version(): void
    {
        Event::fake([ResourceMutated::class]);
        $this->floor->update(['name' => 'Floor light']);

        $response = $this->postJson('/versioned-products/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->desk->id, 'version' => '2026-01-01T08:00:00.000000Z', 'attributes' => ['name' => 'Desk light']],
            ['operation' => 'update', 'key' => $this->floor->id, 'version' => '2026-01-02T08:00:00.000000Z', 'attributes' => ['price' => 99]],
            ['operation' => 'create', 'attributes' => ['category_id' => $this->lighting->id, 'name' => 'Wall lamp', 'price' => 45]],
        ]]);

        $response->assertConflict()->assertExactJson([
            'message' => 'The record was changed by another request.',
            'conflicts' => [['reason' => 'version', 'path' => 'mutate.1', 'key' => $this->floor->id, 'version' => self::NOW]],
        ]);
        $this->assertSame(['Desk lamp', 'Floor light'], $this->names());
        $this->assertSame([39, 39], Product::query()->orderBy('id')->pluck('price')->all());
        $this->assertSame([], VersionedProductResource::$hooks);
        Event::assertNotDispatched(ResourceMutated::class);
    }

    #[Test]
    public function it_writes_an_update_without_a_version_as_before(): void
    {
        $this->desk->update(['price' => 42]);

        $response = $this->postJson('/versioned-products/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->desk->id, 'attributes' => ['name' => 'Desk light']],
        ]]);

        $response->assertOk();
        $this->assertSame(['Desk light', 'Floor lamp'], $this->names());
        $this->assertSame(['mutatingAll', 'mutatedAll'], VersionedProductResource::$hooks);
    }

    #[Test]
    public function it_compares_each_entry_with_the_version_read_before_the_first_write(): void
    {
        $response = $this->postJson('/versioned-products/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->desk->id, 'version' => '2026-01-01T08:00:00.000000Z', 'attributes' => ['name' => 'Desk light']],
            ['operation' => 'update', 'key' => $this->desk->id, 'version' => '2026-01-01T08:00:00.000000Z', 'attributes' => ['price' => 42]],
        ]]);

        $response->assertOk();
        $this->assertSame(['Desk light', 42], [$this->desk->fresh()?->name, $this->desk->fresh()?->price]);
    }

    #[Test]
    public function it_checks_the_version_of_an_entry_under_a_relation(): void
    {
        $this->floor->update(['name' => 'Floor light']);

        $response = $this->postJson('/versioned-categories/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->lighting->id,
            'version' => '2026-01-01T07:00:00.000000Z',
            'attributes' => ['name' => 'Lights'],
            'relations' => ['products' => [
                ['operation' => 'update', 'key' => $this->desk->id, 'version' => '2026-01-01T08:00:00.000000Z', 'attributes' => ['price' => 42]],
                ['operation' => 'update', 'key' => $this->floor->id, 'version' => '2026-01-02T08:00:00.000000Z', 'attributes' => ['price' => 99]],
            ]],
        ]]]);

        $response->assertConflict()->assertExactJson([
            'message' => 'The record was changed by another request.',
            'conflicts' => [['reason' => 'version', 'path' => 'mutate.0.relations.products.1', 'key' => $this->floor->id, 'version' => self::NOW]],
        ]);
        $this->assertSame('Lighting', $this->lighting->fresh()?->name);
        $this->assertSame([39, 39], Product::query()->orderBy('id')->pluck('price')->all());
    }

    #[Test]
    public function it_checks_the_version_against_each_key_of_a_list(): void
    {
        $response = $this->postJson('/versioned-categories/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->lighting->id,
            'relations' => ['products' => [
                ['operation' => 'update', 'key' => [$this->desk->id, $this->floor->id], 'version' => '2026-01-01T08:00:00.000000Z', 'attributes' => ['price' => 42]],
            ]],
        ]]]);

        $response->assertConflict()->assertExactJson([
            'message' => 'The record was changed by another request.',
            'conflicts' => [['reason' => 'version', 'path' => 'mutate.0.relations.products.0', 'key' => $this->floor->id, 'version' => '2026-01-02T08:00:00.000000Z']],
        ]);
    }

    #[Test]
    public function it_answers_a_refused_row_before_a_conflict(): void
    {
        $sealed = $this->product('Sealed lamp', '2026-01-03 08:00:00');

        $response = $this->postJson('/versioned-products/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->desk->id, 'version' => '2026-01-01T00:00:00.000000Z', 'attributes' => ['price' => 42]],
            ['operation' => 'update', 'key' => $sealed->id, 'attributes' => ['price' => 42]],
        ]]);

        $response->assertForbidden()->assertJsonPath('message', 'Sealed products are kept.');
    }

    /** @param array<string, mixed> $entry */
    #[Test]
    #[DataProvider('refusedVersions')]
    public function it_refuses_a_version_that_the_entry_cannot_send(string $uri, array $entry, string $error): void
    {
        $entry = array_map(fn (mixed $value): mixed => $value === 'desk' ? $this->desk->id : $value, $entry);

        $response = $this->postJson($uri, ['mutate' => [$entry]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['mutate.0.version' => [$error]]);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function refusedVersions(): array
    {
        return [
            'a resource that is not versioned' => ['/api/products/mutate', ['operation' => 'update', 'key' => 'desk', 'version' => '2026-01-01T08:00:00.000000Z'], 'The mutate.0.version field is prohibited.'],
            'a create' => ['/versioned-products/mutate', ['operation' => 'create', 'version' => '2026-01-01T08:00:00.000000Z', 'attributes' => ['name' => 'Wall lamp']], 'The mutate.0.version field is prohibited when mutate.0.operation is create.'],
            'a version that is not a string' => ['/versioned-products/mutate', ['operation' => 'update', 'key' => 'desk', 'version' => 1767254400], 'The mutate.0.version field must be a string.'],
            'null' => ['/versioned-products/mutate', ['operation' => 'update', 'key' => 'desk', 'version' => null], 'The mutate.0.version field must be a string.'],
        ];
    }

    #[Test]
    public function it_refuses_a_version_on_an_operation_of_a_relation_other_than_update(): void
    {
        $response = $this->postJson('/versioned-categories/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->lighting->id,
            'relations' => ['products' => [['operation' => 'attach', 'key' => $this->desk->id, 'version' => '2026-01-01T08:00:00.000000Z']]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.products.0.version' => ['The mutate.0.relations.products.0.version field is prohibited when mutate.0.relations.products.0.operation is attach.'],
        ]);
    }

    #[Test]
    public function it_refuses_a_versioned_resource_whose_model_has_no_timestamps(): void
    {
        $this->expectExceptionObject(InvalidResourceException::versionWithoutTimestamps(TimestamplessProductResource::class));

        $this->definitionOf(TimestamplessProductResource::class);
    }

    #[Test]
    public function it_refuses_a_versioned_resource_without_the_field_of_its_version(): void
    {
        $this->expectExceptionObject(InvalidResourceException::versionNotAField(UndeclaredVersionProductResource::class, 'updated_at'));

        $this->definitionOf(UndeclaredVersionProductResource::class);
    }

    /** @param class-string<Resource> $resource */
    private function definitionOf(string $resource): void
    {
        $context = new RestContext(RestRequest::create('/versioned-products/mutate', 'POST'), $this->app);

        $context->resource($resource)
            ->definition()
            ->fields();
    }

    private function product(string $name, string $updatedAt): Product
    {
        return Product::factory()->create(['category_id' => $this->lighting->id, 'name' => $name, 'price' => 39, 'updated_at' => $updatedAt]);
    }

    /** @return list<string> */
    private function names(): array
    {
        return Product::query()
            ->orderBy('id')
            ->pluck('name')
            ->all();
    }
}
