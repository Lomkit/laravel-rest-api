<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Dedoc\Scramble\Generator;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidControllerException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\UndeclaredResourceController;
use Lomkit\Rest\Tests\Feature\Scramble\Stubs\ImmutableTagController;
use Lomkit\Rest\Tests\Feature\Scramble\Stubs\TagController;
use PHPUnit\Framework\Attributes\Test;

class ScrambleDocumentTest extends ScrambleTestCase
{
    #[Test]
    public function it_documents_the_search_body_when_scramble_boots_first(): void
    {
        $operation = $this->operation('/products/search', 'post');

        $this->assertSame('Search Product', $operation['summary']);
        $this->assertSame(
            ['id', 'category_id', 'name', 'price'],
            $this->body($operation)['properties']['search']['properties']['filters']['items']['properties']['field']['enum'],
        );
    }

    #[Test]
    public function it_documents_the_filterable_sortable_and_selectable_fields_of_the_search_body(): void
    {
        $search = $this->body($this->operation('/authors/search', 'post'))['properties']['search']['properties'];

        $this->assertSame(
            ['id', 'first_name', 'last_name', 'full_name', 'created_at'],
            $search['filters']['items']['properties']['field']['enum'],
        );
        $this->assertSame(['first_name', 'last_name', 'full_name', 'created_at'], $search['sorts']['items']['properties']['field']['enum']);
        $this->assertSame(['asc', 'desc'], $search['sorts']['items']['properties']['direction']['enum']);
        $this->assertSame(['id', 'first_name', 'last_name', 'settings'], $search['selects']['items']['properties']['field']['enum']);
        $this->assertSame(['count', 'min', 'max', 'avg', 'sum', 'exists'], $search['aggregates']['items']['properties']['type']['enum']);
    }

    #[Test]
    public function it_documents_the_parameters_and_the_filter_operators_of_the_search_body(): void
    {
        $search = $this->body($this->operation('/products/search', 'post'))['properties']['search']['properties'];
        $filter = $search['filters']['items']['properties'];

        $this->assertSame(['filters', 'sorts', 'selects', 'includes', 'scopes', 'text', 'aggregates', 'instructions', 'gates', 'page', 'limit'], array_keys($search));
        $this->assertSame(
            ['=', '!=', '>', '>=', '<', '<=', 'like', 'not like', 'ilike', 'not ilike', 'in', 'not in', 'between', 'not between', 'is null', 'is not null'],
            $filter['operator']['enum'],
        );
        $this->assertArrayNotHasKey('type', (array) $filter['value']);
    }

    #[Test]
    public function it_documents_the_include_relation_as_a_plain_string_when_the_resource_declares_no_relation(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('tags', TagController::class)->register());

        $includes = $this->body($this->operation('/tags/search', 'post'))['properties']['search']['properties']['includes'];

        $this->assertSame(['type' => 'string'], $includes['items']['properties']['relation']);
    }

    #[Test]
    public function it_documents_the_mutate_body_when_scramble_boots_first(): void
    {
        $operation = $this->operation('/products/mutate', 'post');

        $this->assertSame('Mutate Product', $operation['summary']);
        $this->assertSame(
            ['create', 'update'],
            $this->body($operation)['properties']['mutate']['items']['properties']['operation']['enum'],
        );
    }

    #[Test]
    public function it_documents_sync_and_toggle_on_many_to_many_relations_only(): void
    {
        $products = $this->body($this->operation('/products/mutate', 'post'))['properties']['mutate']['items']['properties']['relations']['properties'];
        $categories = $this->body($this->operation('/categories/mutate', 'post'))['properties']['mutate']['items']['properties']['relations']['properties'];

        $this->assertSame(['create', 'update', 'attach', 'detach'], $products['category']['properties']['operation']['enum']);
        $this->assertSame(['create', 'update', 'attach', 'detach'], $categories['products']['items']['properties']['operation']['enum']);
        $this->assertSame(['create', 'update', 'attach', 'detach', 'sync', 'toggle'], $categories['featuredProducts']['items']['properties']['operation']['enum']);
    }

    #[Test]
    public function it_documents_the_rules_of_the_writable_fields_in_the_mutate_body(): void
    {
        $attributes = $this->body($this->operation('/posts/mutate', 'post'))['properties']['mutate']['items']['properties']['attributes'];

        $writable = 'title, summary, status, featured, views, rating, published_on, published_at, metadata';
        $this->assertSame([
            'type' => 'object',
            'description' => 'Writable on create: '.$writable.'. Writable on update: '.$writable.'.',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'create: string, max:60, required | update: string, max:60, filled'],
                'summary' => ['type' => 'string', 'description' => 'create: nullable, string | update: nullable, string'],
                'status' => ['type' => 'string', 'description' => 'create: required'],
                'featured' => ['type' => 'boolean', 'description' => 'create: boolean | update: boolean'],
                'views' => ['type' => 'integer', 'description' => 'create: integer, min:0 | update: integer, min:0'],
                'rating' => ['type' => 'number', 'description' => 'create: nullable, numeric | update: nullable, numeric'],
                'published_on' => ['type' => 'string', 'description' => 'create: date, required | update: date'],
                'published_at' => ['type' => 'string', 'description' => 'create: nullable, date, after_or_equal:published_on | update: nullable, date, after_or_equal:published_on'],
                'metadata' => ['type' => 'array', 'description' => 'create: nullable, array | update: nullable, array', 'items' => ['type' => 'string']],
            ],
        ], $attributes);
    }

    #[Test]
    public function it_documents_only_the_writable_fields_in_the_mutate_body(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('tags', TagController::class)->register());

        $authors = $this->body($this->operation('/authors/mutate', 'post'))['properties']['mutate']['items']['properties']['attributes'];
        $tags = $this->body($this->operation('/tags/mutate', 'post'))['properties']['mutate']['items']['properties']['attributes'];

        $this->assertSame(['first_name', 'last_name', 'full_name', 'api_token', 'settings'], array_keys($authors['properties']));
        $this->assertSame(['name', 'slug', 'archived_at'], array_keys($tags['properties']));
        $this->assertSame('Writable on create: name, slug. Writable on update: name, archived_at.', $tags['description']);
        $this->assertSame(['type' => 'string', 'description' => 'create: string'], $tags['properties']['slug']);
        $this->assertSame(['type' => 'string', 'description' => 'update: nullable, date'], $tags['properties']['archived_at']);
    }

    #[Test]
    public function it_documents_an_operation_that_writes_no_field(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('immutable-tags', ImmutableTagController::class)->register());

        $tags = $this->body($this->operation('/immutable-tags/mutate', 'post'))['properties']['mutate']['items']['properties']['attributes'];

        $this->assertSame(['name'], array_keys($tags['properties']));
        $this->assertSame('Writable on create: name. Writable on update: none.', $tags['description']);
    }

    #[Test]
    public function it_documents_the_destroy_body_when_scramble_boots_first(): void
    {
        $operation = $this->operation('/products', 'delete');

        $this->assertSame('Delete Product', $operation['summary']);
        $this->assertSame('integer', $this->body($operation)['properties']['resources']['items']['type']);
    }

    #[Test]
    public function it_names_the_controller_without_a_resource_when_it_documents_its_route(): void
    {
        Route::post('api/drafts/search', [UndeclaredResourceController::class, 'search']);
        $this->app['config']->set('app.debug', false);

        $this->expectExceptionObject(new InvalidControllerException(
            'The controller ['.UndeclaredResourceController::class.'] does not declare its resource. Add public static string $resource = YourResource::class; to it.',
        ));

        app(Generator::class)();
    }
}
