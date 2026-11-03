<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Contracts\Translation\Translator;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PricedCategoryController;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\CountedValidator;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\PayloadPostController;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\ValidationCount;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Workspace\Note;
use Lomkit\Rest\Tests\Fixtures\Workspace\Workspace;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class SingleValidatorTest extends PackageTestCase
{
    private ValidationCount $count;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog, new Publishing, new Workspace];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->count = new ValidationCount;
        $this->app['validator']->resolver(fn (Translator $translator, array $data, array $rules, array $messages, array $attributes): CountedValidator => (new CountedValidator($translator, $data, $rules, $messages, $attributes))->countIn($this->count));
        Rest::resource('priced-categories', PricedCategoryController::class)->register();
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
    public function it_validates_a_search_of_every_part_in_one_pass(): void
    {
        Post::factory()
            ->for(Author::factory()->state(['first_name' => 'Ada', 'last_name' => 'Lovelace']))
            ->create(['id' => 1, 'views' => 20]);

        $response = $this->postJson('/api/posts/search', ['search' => [
            'filters' => [
                ['field' => 'views', 'operator' => '>', 'value' => 10],
                ['type' => 'or', 'nested' => [['field' => 'author.first_name', 'value' => 'Ada']]],
            ],
            'sorts' => [['field' => 'views', 'direction' => 'desc'], ['field' => 'id']],
            'selects' => [['field' => 'id'], ['field' => 'title']],
            'scopes' => [['name' => 'popular', 'parameters' => [5]]],
            'instructions' => [['name' => 'highlight-posts', 'fields' => [['name' => 'min_views', 'value' => 50]]]],
            'includes' => [['relation' => 'author', 'alias' => 'writer', 'filters' => [['field' => 'last_name', 'value' => 'Lovelace']]]],
            'gates' => ['view'],
            'limit' => 25,
            'page' => 1,
        ]]);

        $response->assertOk();
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
    public function it_validates_a_mutation_of_every_part_in_one_pass(): void
    {
        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);
        $deskLamp = Product::factory()->create(['category_id' => 1, 'name' => 'Desk lamp']);
        $floorLamp = Product::factory()->create(['category_id' => 1, 'name' => 'Floor lamp']);

        $response = $this->postJson('/priced-categories/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 1,
            'attributes' => ['name' => 'Lamps'],
            'relations' => [
                'products' => [
                    ['operation' => 'attach', 'key' => [$floorLamp->id]],
                    ['operation' => 'create', 'attributes' => ['name' => 'Floor lamp', 'price' => 129]],
                ],
                'featuredProducts' => [['operation' => 'attach', 'key' => $deskLamp->id, 'pivot' => ['price' => 10]]],
            ],
        ]]]);

        $created = Product::query()
            ->whereKeyNot([$deskLamp->id, $floorLamp->id])
            ->sole();
        $response->assertOk()->assertExactJson(['operations' => [[
            'operation' => 'update',
            'key' => 1,
            'relations' => [
                'products' => [
                    ['operation' => 'attach', 'key' => [$floorLamp->id]],
                    ['operation' => 'create', 'key' => $created->id],
                ],
                'featuredProducts' => [['operation' => 'attach', 'key' => $deskLamp->id]],
            ],
        ]]]);
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

    #[Test]
    public function it_validates_a_deletion_in_one_pass(): void
    {
        Post::factory()->create(['id' => 1, 'title' => 'Annual report']);
        Post::factory()->create(['id' => 2, 'title' => 'Quarterly report']);

        $response = $this->deleteJson('/api/posts', ['resources' => [1, 2]]);

        $response->assertOk();
        $this->assertSame(0, Post::query()->count());
        $this->assertOnePass();
    }

    #[Test]
    public function it_validates_a_targeted_operation_in_one_pass(): void
    {
        Note::factory()->create(['id' => 1, 'team' => 'red', 'title' => 'Standup']);
        Note::factory()->create(['id' => 2, 'team' => 'red', 'title' => 'Retro']);

        $response = $this->postJson('/api/team-notes/actions/pin-named-notes', ['resources' => [1, 2]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2]]);
        $this->assertOnePass();
    }

    #[Test]
    public function it_validates_a_standalone_operation_in_one_pass(): void
    {
        Rest::resource('payload-posts', PayloadPostController::class)->register();

        $response = $this->postJson('/payload-posts/actions/attach-payload', ['fields' => [['name' => 'reason', 'value' => 'spam']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0]]);
        $this->assertOnePass();
    }

    private function assertOnePass(): void
    {
        $this->assertSame(1, $this->count->validators);
        $this->assertSame(1, $this->count->passes);
    }
}
