<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Dedoc\Scramble\Generator;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidControllerException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\ConstrainedCategoryController;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\UndeclaredResourceController;
use Lomkit\Rest\Tests\Feature\Scramble\Stubs\ImmutableTagController;
use Lomkit\Rest\Tests\Feature\Scramble\Stubs\TagController;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\CuratedCategoryController;
use PHPUnit\Framework\Attributes\Test;

class ScrambleDocumentTest extends ScrambleTestCase
{
    #[Test]
    public function it_documents_the_search_body_when_scramble_boots_first(): void
    {
        $operation = $this->operation('/products/search', 'post');

        $this->assertSame('Search Product', $operation['summary']);
        $this->assertSame(['id', 'category_id', 'name', 'price', 'category.id', 'category.name'], array_keys((array) $this->schemas()['ProductFieldFilter']['discriminator']['mapping']));
    }

    #[Test]
    public function it_documents_the_filterable_sortable_and_selectable_fields_of_the_search_body(): void
    {
        $schemas = $this->schemas();

        $this->assertSame(['id', 'first_name', 'last_name', 'full_name', 'posts.id', 'posts.title', 'posts.summary', 'posts.status', 'posts.featured', 'posts.views', 'posts.rating', 'posts.published_on', 'posts.published_at', 'drafts.id', 'drafts.title', 'tags.id', 'tags.name', 'tags.slug', 'tags.archived_at', 'tags.created_at'], array_keys((array) $schemas['AuthorFieldFilter']['discriminator']['mapping']));
        $this->assertSame(['first_name', 'last_name', 'full_name', 'posts_count', 'posts_min_id'], array_slice($schemas['AuthorSort']['properties']['field']['enum'], 0, 5));
        $this->assertSame(['asc', 'desc'], $schemas['AuthorSort']['properties']['direction']['enum']);
        $this->assertSame(['id', 'first_name', 'last_name', 'settings'], $schemas['AuthorSelect']['properties']['field']['enum']);
        $this->assertSame([['count', 'exists'], ['min', 'max'], ['avg', 'sum']], array_map(fn (array $branch): array => $branch['properties']['type']['enum'], $schemas['AuthorAggregatePosts']['oneOf']));
    }

    #[Test]
    public function it_documents_the_parameters_and_the_filter_operators_of_the_search_body(): void
    {
        $price = $this->schemas()['ProductFilterPrice']['oneOf'];

        $this->assertSame(['filters', 'gates', 'sorts', 'selects', 'aggregates', 'includes', 'instructions', 'limit', 'page'], array_keys($this->search('/products/search')));
        $this->assertSame([['=', '!=', '>', '>=', '<', '<='], ['between', 'not between'], ['in', 'not in']], array_map(fn (array $branch): array => $branch['properties']['operator']['enum'], $price));
        $this->assertSame(['type' => 'integer'], $price[0]['properties']['value']);
    }

    #[Test]
    public function it_documents_the_keys_of_the_details_response(): void
    {
        $details = $this->detailsSchema('/posts')['properties']['data'];

        $this->assertSame(['actions', 'metrics', 'instructions', 'fields', 'limits', 'scopes', 'relations', 'rules', 'text', 'gates', 'authorizations', 'meta'], array_keys($details['properties']));
        $this->assertSame(['actions', 'metrics', 'instructions', 'fields', 'limits', 'scopes', 'relations', 'rules', 'text', 'gates', 'authorizations', 'meta'], $details['required']);
    }

    #[Test]
    public function it_documents_no_include_when_the_resource_declares_no_relation(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('tags', TagController::class)->register());

        $this->assertArrayNotHasKey('includes', $this->search('/tags/search'));
    }

    #[Test]
    public function it_documents_the_mutate_body_when_scramble_boots_first(): void
    {
        $operation = $this->operation('/products/mutate', 'post');

        $this->assertSame('Mutate Product', $operation['summary']);
        $this->assertSame(['$ref' => '#/components/schemas/ProductMutation'], $this->body($operation)['properties']['mutate']['items']);
        $this->assertSame(['create', 'update'], array_keys((array) $this->schemas()['ProductMutation']['discriminator']['mapping']));
    }

    #[Test]
    public function it_documents_sync_and_toggle_on_many_to_many_relations_only(): void
    {
        $schemas = $this->schemas();
        $operations = fn (string $name): array => array_map(fn (array $branch): array => $branch['properties']['operation']['enum'], $schemas[$name]['oneOf']);

        $this->assertSame([['create'], ['update'], ['attach', 'detach']], $operations('ProductRelationMutationCategory'));
        $this->assertSame([['create'], ['update'], ['attach', 'detach']], $operations('CategoryRelationMutationProducts'));
        $this->assertSame([['create'], ['update', 'toggle'], ['attach', 'detach'], ['sync']], $operations('CategoryRelationMutationFeaturedProducts'));
    }

    #[Test]
    public function it_documents_the_result_of_each_mutation(): void
    {
        $schema = $this->operation('/categories/mutate', 'post')['responses'][200]['content']['application/json']['schema'];

        $this->assertSame(['$ref' => '#/components/schemas/CategoryMutateResult'], $schema);
        $this->assertSame(['operations', 'records'], array_keys($this->schemas()['CategoryMutateResult']['properties']));
    }

    #[Test]
    public function it_documents_the_type_and_the_rules_of_the_writable_fields_in_the_mutate_body(): void
    {
        $this->assertSame(
            '{"type":"object","description":"The fields that `create` writes","properties":{"title":{"type":"string","description":"Rules: string, max:60, required"},"summary":{"type":["string","null"],"description":"Rules: nullable, string"},"status":{"type":"string","description":"Rules: enum, required","enum":["draft","review","published"]},"featured":{"type":"boolean","description":"Rules: boolean"},"views":{"type":"integer","description":"Rules: integer, min:0"},"rating":{"type":["number","null"],"description":"Rules: nullable, numeric"},"published_on":{"type":"string","format":"date","description":"Rules: date, required"},"published_at":{"type":["string","null"],"format":"date-time","description":"Rules: nullable, date, after_or_equal:published_on"},"metadata":{"type":["object","null"],"description":"Rules: nullable, array, max_depth:64"}},"additionalProperties":false,"title":"PostCreate"}',
            json_encode($this->schemas()['PostCreate']),
        );
    }

    #[Test]
    public function it_documents_only_the_writable_fields_in_the_mutate_body(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('tags', TagController::class)->register());

        $schemas = $this->schemas();

        $this->assertSame(['first_name', 'last_name', 'full_name', 'api_token', 'settings'], array_keys($schemas['AuthorCreate']['properties']));
        $this->assertSame(['name', 'slug'], array_keys($schemas['TagCreate']['properties']));
        $this->assertSame(['name', 'archived_at'], array_keys($schemas['TagUpdate']['properties']));
        $this->assertSame(['type' => 'string', 'description' => 'Rules: string'], $schemas['TagCreate']['properties']['slug']);
        $this->assertSame(['type' => ['string', 'null'], 'format' => 'date-time', 'description' => 'Rules: nullable, date'], $schemas['TagUpdate']['properties']['archived_at']);
    }

    #[Test]
    public function it_documents_an_operation_that_writes_no_field(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('immutable-tags', ImmutableTagController::class)->register());

        $schemas = $this->schemas();

        $this->assertSame(['name'], array_keys($schemas['ImmutableTagCreate']['properties']));
        $this->assertArrayNotHasKey('ImmutableTagUpdate', $schemas);
        $this->assertSame(['operation', 'key'], array_keys($schemas['ImmutableTagMutationUpdate']['properties']));
    }

    #[Test]
    public function it_requires_or_leaves_out_the_relations_that_a_resource_constrains(): void
    {
        Route::prefix('api')->group(function (): void {
            Rest::resource('constrained-categories', ConstrainedCategoryController::class)->register();
            Rest::resource('curated-categories', CuratedCategoryController::class)->register();
        });

        $schemas = $this->schemas();

        $this->assertSame(['$ref' => '#/components/schemas/ConstrainedCategoryCreateRelations'], $schemas['ConstrainedCategoryMutationCreate']['properties']['relations']);
        $this->assertSame(['products', 'featuredProducts'], $schemas['ConstrainedCategoryCreateRelations']['required']);
        $this->assertSame(['$ref' => '#/components/schemas/ConstrainedCategoryRelations'], $schemas['ConstrainedCategoryMutationUpdate']['properties']['relations']);
        $this->assertArrayNotHasKey('required', $schemas['ConstrainedCategoryRelations']);
        $this->assertSame(['products'], array_keys($schemas['CuratedCategoryCreateRelations']['properties']));
        $this->assertSame(['products', 'featuredProducts'], array_keys($schemas['CuratedCategoryRelations']['properties']));
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
