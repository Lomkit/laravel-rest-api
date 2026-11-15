<?php

namespace Lomkit\Rest\Tests\Feature\Extensions;

use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\ExtensionFactory;
use Lomkit\Rest\Extensions\ExtensionList;
use Lomkit\Rest\Extensions\NamedExtensions;
use Lomkit\Rest\Extensions\RuntimeExtensions;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CountedFieldsProductResource;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\RoutedTicketController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\PackageTestCase;
use Lomkit\Rest\Text\TextBackend;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;

final class NoExtensionTest extends PackageTestCase
{
    #[Before]
    #[After]
    public function resetTheLog(): void
    {
        ExtensionLog::reset();
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[Test]
    public function it_builds_no_extension_without_one_configured(): void
    {
        Category::forceCreate(['id' => 1, 'name' => 'Lighting']);
        Product::forceCreate(['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp', 'price' => 39]);
        $resolved = [];
        $this->app->beforeResolving(function (string $abstract) use (&$resolved): void {
            $resolved[] = $abstract;
        });

        $response = $this->postJson('/api/products/search', ['search' => ['limit' => 10]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp', 'price' => 39]]);
        $this->assertSame([], array_values(array_filter(
            $resolved,
            fn (string $abstract): bool => class_exists($abstract) && is_subclass_of($abstract, Extension::class),
        )));
        $this->assertContains(RuntimeExtensions::class, $resolved);
        $this->assertSame([], ExtensionLog::$built);
        $this->assertSame([], $this->app->make(RuntimeExtensions::class)->all());
    }

    #[Test]
    public function it_returns_the_declared_fields_without_an_extension(): void
    {
        $definition = (new RestContext(RestRequest::create('/api/products/search', 'POST'), $this->app))
            ->resource(ProductResource::class)
            ->definition();

        $this->assertSame($definition->declaredFields(), $definition->fields());
        $this->assertSame([], ExtensionLog::$built);
    }

    #[Test]
    public function it_resolves_the_text_search_without_reading_the_fields(): void
    {
        CountedFieldsProductResource::$reads = 0;
        $definition = (new RestContext(RestRequest::create('/api/products/search', 'POST'), $this->app))
            ->resource(CountedFieldsProductResource::class)
            ->definition();

        $text = $definition->text();

        $this->assertSame(TextBackend::None, $text->backend);
        $this->assertSame(0, CountedFieldsProductResource::$reads);
        $this->assertSame([], ExtensionLog::$built);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_registers_routes_without_loading_the_extension_list(): void
    {
        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertNotNull($this->app['router']->getRoutes()->getByName('routed-tickets.search'));
        $this->assertSame([false, false, false], [
            class_exists(ExtensionList::class, false),
            class_exists(NamedExtensions::class, false),
            class_exists(ExtensionFactory::class, false),
        ]);
    }
}
