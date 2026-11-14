<?php

namespace Lomkit\Rest\Tests\Feature\Extensions;

use Illuminate\Support\Arr;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\RuntimeExtensions;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
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
    public function it_answers_with_a_cached_config_that_has_no_extensions_key(): void
    {
        $rest = $this->app['config']->get('rest');
        $this->assertIsArray($rest);
        $this->app['config']->set('rest', Arr::except($rest, 'extensions'));
        Category::forceCreate(['id' => 1, 'name' => 'Lighting']);
        Product::forceCreate(['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp', 'price' => 39]);

        $response = $this->withoutExceptionHandling()->postJson('/api/products/search', ['search' => ['limit' => 10]]);

        $response->assertOk()->assertJsonPath('data', [['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp', 'price' => 39]]);
        $this->assertSame([], ExtensionLog::$built);
    }
}
