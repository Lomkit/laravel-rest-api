<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class MalformedMutationPayloadTest extends PackageTestCase
{
    use AssertsExactErrors;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);
        Product::factory()->create(['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp', 'price' => 39]);
    }

    #[Test]
    public function it_refuses_a_mutate_list_given_as_an_object(): void
    {
        $response = $this->postJson('/api/categories/mutate', ['mutate' => ['first' => ['operation' => 'create', 'attributes' => ['name' => 'Seating']]]]);

        $this->assertExactErrors($response, ['mutate' => ['The mutate field must be a list.']]);
        $this->assertSame(['Lighting'], $this->categoryNames());
    }

    /** @return array<int, mixed> */
    private function categoryNames(): array
    {
        return Category::query()
            ->pluck('name')
            ->all();
    }
}
