<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\CuratedCategoryController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RelationKeysTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[Test]
    public function it_names_a_relation_as_written_in_its_errors(): void
    {
        Rest::resource('curated-categories', CuratedCategoryController::class)->register();
        $deskLamp = Product::factory()->create(['name' => 'Desk lamp']);

        $response = $this->postJson('/curated-categories/mutate', ['mutate' => [$this->creationWithFeatured($deskLamp)]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.featuredProducts' => ['The mutate.0.relations.featuredProducts field is prohibited.'],
        ]);
    }

    #[Test]
    public function it_validates_the_mutation_sent_and_not_a_root_key_holding_its_path(): void
    {
        Rest::resource('curated-categories', CuratedCategoryController::class)->register();
        $deskLamp = Product::factory()->create(['name' => 'Desk lamp']);

        $response = $this->postJson('/curated-categories/mutate', [
            'mutate' => [$this->creationWithFeatured($deskLamp)],
            'mutate.0' => ['operation' => 'update', 'key' => $deskLamp->category_id],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.featuredProducts' => ['The mutate.0.relations.featuredProducts field is prohibited.'],
        ]);
        $this->assertSame(1, Category::query()->count());
    }

    #[Test]
    public function it_names_the_entries_of_a_to_many_relation_as_written_in_their_errors(): void
    {
        $response = $this->postJson('/api/categories/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['name' => 'Lighting'],
            'relations' => ['featuredProducts' => [['operation' => 'replace']]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.featuredProducts.0.operation' => ['The selected mutate.0.relations.featuredProducts.0.operation is invalid.'],
        ]);
    }

    #[Test]
    public function it_names_the_entries_of_a_to_many_relation_with_their_translation(): void
    {
        $this->app->make('translator')->load('*', 'validation', 'en');
        $this->app->make('translator')->addLines(['validation.attributes.mutate.*.relations.featuredProducts.*.operation' => 'featured product operation'], 'en');

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['name' => 'Lighting'],
            'relations' => ['featuredProducts' => [['operation' => 'replace']]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.featuredProducts.0.operation' => ['The selected featured product operation is invalid.'],
        ]);
    }

    /** @return array<string, mixed> */
    private function creationWithFeatured(Product $product): array
    {
        return [
            'operation' => 'create',
            'attributes' => ['name' => 'Seating'],
            'relations' => ['featuredProducts' => [['operation' => 'attach', 'key' => $product->id]]],
        ];
    }
}
