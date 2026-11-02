<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\ConstrainedCategoryController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class MutateErrorOrderTest extends PackageTestCase
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

        Rest::resource('constrained-categories', ConstrainedCategoryController::class)->register();
        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);
        Product::factory()->create(['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp', 'price' => 39]);
    }

    #[Test]
    public function it_orders_the_errors_of_an_entry_as_its_keys(): void
    {
        $response = $this->postJson('/api/categories/mutate', ['mutate' => [[
            'operation' => 'create',
            'key' => 1,
            'attributes' => ['name' => 5, 'slug' => 'lighting'],
            'without_detaching' => true,
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.name' => ['The mutate.0.attributes.name field must be a string.'],
            'mutate.0.attributes' => ['The slug attribute is not writable on create.'],
            'mutate.0.key' => ['The mutate.0.key field is prohibited when mutate.0.operation is create.'],
            'mutate.0.without_detaching' => ['The mutate.0.without detaching field is prohibited unless mutate.0.operation is in sync.'],
        ]);
    }

    #[Test]
    public function it_reports_the_pivot_of_an_entry_after_its_relations(): void
    {
        $response = $this->postJson('/api/categories/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 1,
            'relations' => ['featuredProducts' => [[
                'operation' => 'attach',
                'key' => 1,
                'attributes' => ['name' => 'Desk lamp'],
                'pivot' => ['rank' => 1],
                'relations' => ['category' => ['operation' => 'detach']],
            ]]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.featuredProducts.0.attributes' => ['The mutate.0.relations.featuredProducts.0.attributes field is prohibited when mutate.0.relations.featuredProducts.0.operation is attach.'],
            'mutate.0.relations.featuredProducts.0.relations.category.key' => ['The mutate.0.relations.featured products.0.relations.category.key field must be present when mutate.0.relations.featured products.0.relations.category.operation is detach.'],
            'mutate.0.relations.featuredProducts.0.pivot' => ['The rank attribute is not writable on create.'],
        ]);
    }

    #[Test]
    public function it_reports_the_relation_constraints_of_an_entry_first(): void
    {
        $response = $this->postJson('/constrained-categories/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['name' => 5],
            'relations' => ['featuredProducts' => 'x'],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.products' => ['The mutate.0.relations.products field is required.'],
            'mutate.0.relations.featuredProducts' => ['The mutate.0.relations.featuredProducts field must be an array.'],
            'mutate.0.attributes.name' => ['The mutate.0.attributes.name field must be a string.'],
        ]);
    }

    #[Test]
    public function it_names_a_missing_relation_as_laravel_does(): void
    {
        $response = $this->postJson('/constrained-categories/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['name' => 'Seating'],
            'relations' => ['products' => []],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.products' => ['The mutate.0.relations.products field is required.'],
            'mutate.0.relations.featuredProducts' => ['The mutate.0.relations.featured products field is required.'],
        ]);
    }
}
