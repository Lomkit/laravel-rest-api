<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\UpdateConstrainedCategoryController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RequiredRelationsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('update-constrained-categories', UpdateConstrainedCategoryController::class)->register();
        Category::query()->insert(['id' => 101, 'name' => 'Lighting']);
        Product::query()->insert(['id' => 101, 'category_id' => 101, 'name' => 'Desk lamp', 'price' => 39]);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('missingRelations')]
    public function it_refuses_an_update_without_a_relation_required_on_update(array $entry, array $errors): void
    {
        $response = $this->postJson('/update-constrained-categories/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 101,
            'attributes' => ['name' => 'Lamps'],
            ...$entry,
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
        $this->assertSame(['Lighting'], Category::query()
            ->pluck('name')
            ->all());
    }

    /** @return array<string, array{array<string, mixed>, array<string, list<string>>}> */
    public static function missingRelations(): array
    {
        return [
            'missing' => [[], ['mutate.0.relations.products' => ['The mutate.0.relations.products field is required.']]],
            'an empty list' => [['relations' => ['products' => []]], ['mutate.0.relations.products' => ['The mutate.0.relations.products field is required.']]],
        ];
    }

    #[Test]
    public function it_updates_a_row_with_its_required_relation(): void
    {
        $response = $this->postJson('/update-constrained-categories/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 101,
            'attributes' => ['name' => 'Lamps'],
            'relations' => ['products' => [['operation' => 'update', 'key' => 101, 'attributes' => ['price' => 45]]]],
        ]]]);

        $response->assertOk();
        $this->assertSame(['Lamps'], Category::query()
            ->pluck('name')
            ->all());
        $this->assertSame([45], Product::query()
            ->pluck('price')
            ->all());
    }
}
