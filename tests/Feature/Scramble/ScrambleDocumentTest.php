<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidControllerException;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\UndeclaredResourceController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ScrambleDocumentTest extends PackageTestCase
{
    protected function setUp(): void
    {
        Scramble::$extensions = [];

        parent::setUp();
    }

    protected function fixtures(): array
    {
        return [new Catalog, new Publishing];
    }

    protected function getPackageProviders($app): array
    {
        return [ScrambleServiceProvider::class, RestServiceProvider::class];
    }

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
    public function it_documents_the_sortable_and_selectable_fields_of_the_search_body(): void
    {
        $search = $this->body($this->operation('/authors/search', 'post'))['properties']['search']['properties'];

        $this->assertSame(
            ['id', 'first_name', 'last_name', 'full_name', 'initials', 'api_token', 'settings', 'created_at'],
            $search['filters']['items']['properties']['field']['enum'],
        );
        $this->assertSame(['first_name', 'last_name', 'full_name', 'created_at'], $search['sorts']['items']['properties']['field']['enum']);
        $this->assertSame(['asc', 'desc'], $search['sorts']['items']['properties']['direction']['enum']);
        $this->assertSame(['id', 'first_name', 'last_name', 'settings'], $search['selects']['items']['properties']['field']['enum']);
        $this->assertSame(['count', 'min', 'max', 'avg', 'sum', 'exists'], $search['aggregates']['items']['properties']['type']['enum']);
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

    /** @return array<string, mixed> */
    private function operation(string $path, string $method): array
    {
        return app(Generator::class)()['paths'][$path][$method];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function body(array $operation): array
    {
        return $operation['requestBody']['content']['application/json']['schema'];
    }
}
