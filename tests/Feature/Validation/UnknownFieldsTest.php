<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Foundation\Http\FormRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class UnknownFieldsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog, new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        FormRequest::failOnUnknownFields();
    }

    #[\Override]
    protected function tearDown(): void
    {
        FormRequest::failOnUnknownFields(false);

        parent::tearDown();
    }

    #[Test]
    public function it_validates_a_mutation_when_unknown_fields_fail_for_every_request(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);

        $response = $this->postJson('/api/products/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Desk lamp', 'price' => 39, 'category_id' => $lighting->id]],
        ]]);

        $response->assertOk();
        $this->assertSame(['Desk lamp'], Product::query()->pluck('name')->all());
    }

    #[Test]
    public function it_validates_a_search_when_unknown_fields_fail_for_every_request(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        $deskLamp = Product::factory()->for($lighting)->create(['name' => 'Desk lamp', 'price' => 39]);
        Product::factory()->for($lighting)->create(['name' => 'Floor lamp', 'price' => 129]);

        $response = $this->postJson('/api/products/search', ['search' => [
            'filters' => [['field' => 'price', 'operator' => '<', 'value' => 100]],
            'selects' => [['field' => 'id'], ['field' => 'name']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [['id' => $deskLamp->id, 'name' => 'Desk lamp']]);
    }

    #[Test]
    public function it_validates_an_operation_when_unknown_fields_fail_for_every_request(): void
    {
        $draft = Post::factory()->create(['title' => 'Annual report', 'status' => PostStatus::Draft]);

        $response = $this->postJson('/api/posts/actions/change-status', [
            'search' => ['filters' => [['field' => 'title', 'value' => 'Annual report']]],
            'fields' => [['name' => 'status', 'value' => 'published'], ['name' => 'at', 'value' => '2026-03-01 10:00:00']],
        ]);

        $response->assertOk();
        $this->assertSame(PostStatus::Published, $draft->fresh()?->status);
    }
}
