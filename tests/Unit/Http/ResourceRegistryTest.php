<?php

namespace Lomkit\Rest\Tests\Unit\Http;

use Illuminate\Container\Container;
use Lomkit\Rest\Http\ResourceRegistry;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResourceRegistryTest extends TestCase
{
    #[Test]
    public function it_returns_one_instance_per_resource_class(): void
    {
        $registry = new ResourceRegistry(new Container);

        $this->assertSame($registry->get(CategoryResource::class), $registry->get(CategoryResource::class));
        $this->assertNotSame($registry->get(CategoryResource::class), $registry->get(ProductResource::class));
    }

    #[Test]
    public function it_keeps_the_instances_of_each_registry_apart(): void
    {
        $container = new Container;

        $this->assertNotSame(
            (new ResourceRegistry($container))->get(CategoryResource::class),
            (new ResourceRegistry($container))->get(CategoryResource::class),
        );
    }
}
