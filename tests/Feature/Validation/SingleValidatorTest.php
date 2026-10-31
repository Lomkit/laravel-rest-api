<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Contracts\Translation\Translator;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\CountedValidator;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\ValidationCount;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class SingleValidatorTest extends PackageTestCase
{
    private ValidationCount $count;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog, new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->count = new ValidationCount;
        $this->app['validator']->resolver(fn (Translator $translator, array $data, array $rules, array $messages, array $attributes): CountedValidator => (new CountedValidator($translator, $data, $rules, $messages, $attributes))->countIn($this->count));
    }

    #[Test]
    public function it_validates_a_nested_search_in_one_pass(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        $deskLamp = Product::factory()->for($lighting)->create(['name' => 'Desk lamp', 'price' => 39]);
        Product::factory()->for($lighting)->create(['name' => 'Floor lamp', 'price' => 129]);

        $response = $this->postJson('/api/products/search', ['search' => [
            'filters' => [
                ['field' => 'name', 'operator' => 'like', 'value' => '%lamp'],
                ['nested' => [['field' => 'price', 'operator' => '<', 'value' => 100], ['field' => 'price', 'operator' => '>', 'value' => 10]]],
            ],
            'sorts' => [['field' => 'price', 'direction' => 'asc']],
            'selects' => [['field' => 'id'], ['field' => 'name']],
            'includes' => [['relation' => 'category', 'filters' => [['field' => 'name', 'value' => 'Lighting']]]],
            'instructions' => [['name' => 'priced-at', 'fields' => [['name' => 'price', 'value' => 39]]]],
        ]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => $deskLamp->id, 'name' => 'Desk lamp', 'category' => ['id' => $lighting->id, 'name' => 'Lighting']],
        ]);
        $this->assertOnePass();
    }

    #[Test]
    public function it_reports_the_errors_of_every_depth_from_one_pass(): void
    {
        $response = $this->postJson('/api/products/search', ['search' => [
            'filters' => [['nested' => [['field' => 'stock', 'value' => 3]]]],
            'includes' => [['relation' => 'category', 'filters' => [['field' => 'name', 'operator' => 'near', 'value' => 'Lighting']]]],
            'instructions' => [['name' => 'priced-at', 'fields' => [['name' => 'price', 'value' => 'cheap']]]],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.filters.0.nested.0.field' => ['The selected search.filters.0.nested.0.field is invalid.'],
            'search.includes.0.filters.0.operator' => ['The selected search.includes.0.filters.0.operator is invalid.'],
            'search.instructions.0.fields.price' => ['The price field must be an integer.'],
        ]);
        $this->assertOnePass();
    }

    #[Test]
    public function it_validates_a_mutation_with_nested_relations_in_one_pass(): void
    {
        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Lighting'], 'relations' => ['products' => [
                ['operation' => 'create', 'attributes' => ['name' => 'Desk lamp', 'price' => 39]],
                ['operation' => 'create', 'attributes' => ['name' => 'Floor lamp', 'price' => 129]],
            ]]],
            ['operation' => 'create', 'attributes' => ['name' => 'Seating']],
        ]]);

        $response->assertOk();
        $this->assertSame(['Desk lamp', 'Floor lamp'], Product::query()->orderBy('id')->pluck('name')->all());
        $this->assertOnePass();
    }

    #[Test]
    public function it_validates_an_operation_with_fields_and_a_search_in_one_pass(): void
    {
        $draft = Post::factory()->create(['title' => 'Annual report', 'status' => PostStatus::Draft]);

        $response = $this->postJson('/api/posts/actions/change-status', [
            'search' => ['filters' => [['field' => 'title', 'value' => 'Annual report']]],
            'fields' => [['name' => 'status', 'value' => 'published'], ['name' => 'at', 'value' => '2026-03-01 10:00:00']],
        ]);

        $response->assertOk();
        $this->assertSame(PostStatus::Published, $draft->fresh()?->status);
        $this->assertOnePass();
    }

    private function assertOnePass(): void
    {
        $this->assertSame(1, $this->count->validators);
        $this->assertSame(1, $this->count->passes);
    }
}
