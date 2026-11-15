<?php

namespace Lomkit\Rest\Tests\Unit\Extensions;

use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\ExtensionFactory;
use Lomkit\Rest\Extensions\ExtensionList;
use Lomkit\Rest\Extensions\ExtensionRegistry;
use Lomkit\Rest\Extensions\Kinds\ExtensionKind;
use Lomkit\Rest\Extensions\NamedExtensions;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\Fixtures\Extensions\AclExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\AppAclExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\BothKindsExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountsResources;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\KindlessExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotesResources;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ProductsOnlyExtension;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExtensionRegistryTest extends TestCase
{
    #[Before]
    #[After]
    public function resetTheLog(): void
    {
        ExtensionLog::reset();
    }

    #[Test]
    public function it_touches_nothing_without_extensions(): void
    {
        $container = $this->createMock(Container::class);
        $container->expects($this->never())->method($this->anything());

        $registry = ExtensionRegistry::of([], [], [], $container);

        $this->assertSame([], $registry->all());
        $this->assertSame([], $registry->applicable(ProductResource::class, NotesResources::class));
        $this->assertSame([], ExtensionLog::$built);
        $this->assertSame([], ExtensionLog::$asked);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_loads_no_list_class_without_extensions(): void
    {
        $container = $this->createMock(Container::class);
        $container->expects($this->never())->method($this->anything());

        $this->assertSame([], ExtensionRegistry::of([], [], [], $container)->all());
        $this->assertFalse(class_exists(ExtensionList::class, false));
        $this->assertFalse(class_exists(NamedExtensions::class, false));
        $this->assertFalse(class_exists(ExtensionFactory::class, false));
    }

    #[Test]
    public function it_builds_nothing_when_every_entry_is_disabled(): void
    {
        $container = $this->createMock(Container::class);
        $container->expects($this->never())->method($this->anything());

        $registry = ExtensionRegistry::of([], [NotingExtension::class], [NotingExtension::class], $container);

        $this->assertSame([], $registry->all());
        $this->assertSame([], $registry->applicable(ProductResource::class, NotesResources::class));
        $this->assertSame([], ExtensionLog::$built);
    }

    #[Test]
    public function it_builds_nothing_before_first_use(): void
    {
        $registry = ExtensionRegistry::of([], [], [NotingExtension::class], new IlluminateContainer);

        $this->assertSame([], ExtensionLog::$built);
    }

    #[Test]
    public function it_builds_each_extension_once(): void
    {
        $registry = ExtensionRegistry::of([], [], [NotingExtension::class], new IlluminateContainer);

        $first = $registry->all();
        $second = $registry->all();
        $applicable = $registry->applicable(ProductResource::class, NotesResources::class);

        $this->assertSame([NotingExtension::class], ExtensionLog::$built);
        $this->assertSame($first['noting'], $second['noting']);
        $this->assertSame($first['noting'], $applicable['noting']);
    }

    #[Test]
    public function it_gives_the_extensions_of_one_kind_in_name_order(): void
    {
        $registry = ExtensionRegistry::of([], [], [NotingExtension::class, CountingExtension::class, BothKindsExtension::class], new IlluminateContainer);

        $this->assertSame(['both', 'noting'], array_keys($registry->applicable(ProductResource::class, NotesResources::class)));
        $this->assertSame(['both', 'counting'], array_keys($registry->applicable(ProductResource::class, CountsResources::class)));
        $this->assertSame(['noting', 'counting', 'both'], array_keys($registry->all()));
    }

    #[Test]
    public function it_gives_config_extensions_in_name_order(): void
    {
        $registry = ExtensionRegistry::of([NotingExtension::class, BothKindsExtension::class], [], [], new IlluminateContainer);

        $this->assertSame(['both', 'noting'], array_keys($registry->applicable(ProductResource::class, NotesResources::class)));
        $this->assertSame([NotingExtension::class, BothKindsExtension::class], ExtensionLog::$built);
    }

    #[Test]
    public function it_leaves_out_an_extension_that_does_not_apply_to_the_resource(): void
    {
        $registry = ExtensionRegistry::of([], [], [ProductsOnlyExtension::class, NotingExtension::class], new IlluminateContainer);

        $this->assertSame(['noting', 'products'], array_keys($registry->applicable(ProductResource::class, NotesResources::class)));
        $this->assertSame(['noting'], array_keys($registry->applicable(CategoryResource::class, NotesResources::class)));
    }

    #[Test]
    public function it_asks_applies_to_once_per_resource_in_one_registry(): void
    {
        $registry = ExtensionRegistry::of([], [], [ProductsOnlyExtension::class], new IlluminateContainer);
        $registry->applicable(ProductResource::class, NotesResources::class);
        $registry->applicable(ProductResource::class, NotesResources::class);
        $registry->applicable(CategoryResource::class, NotesResources::class);

        $this->assertSame([
            ProductsOnlyExtension::class.' '.ProductResource::class,
            ProductsOnlyExtension::class.' '.CategoryResource::class,
        ], ExtensionLog::$asked);
    }

    #[Test]
    public function it_asks_applies_to_again_in_another_registry(): void
    {
        foreach ([new IlluminateContainer, new IlluminateContainer] as $container) {
            $registry = ExtensionRegistry::of([], [], [ProductsOnlyExtension::class], $container);
            $registry->applicable(ProductResource::class, NotesResources::class);
            $registry->applicable(ProductResource::class, NotesResources::class);
            $registry->applicable(CategoryResource::class, NotesResources::class);
        }

        $this->assertSame([ProductsOnlyExtension::class, ProductsOnlyExtension::class], ExtensionLog::$built);
        $this->assertSame([
            ProductsOnlyExtension::class.' '.ProductResource::class,
            ProductsOnlyExtension::class.' '.CategoryResource::class,
            ProductsOnlyExtension::class.' '.ProductResource::class,
            ProductsOnlyExtension::class.' '.CategoryResource::class,
        ], ExtensionLog::$asked);
    }

    #[Test]
    public function it_gives_every_extension_of_one_kind_in_name_order_without_asking_applies_to(): void
    {
        $registry = ExtensionRegistry::of([], [
            'products' => [ProductsOnlyExtension::class],
            'counting' => [CountingExtension::class],
            'both' => [BothKindsExtension::class],
        ], new IlluminateContainer);

        $this->assertSame(['both', 'products'], array_keys($registry->ofKind(NotesResources::class)));
        $this->assertSame([], ExtensionLog::$asked);
    }

    #[Test]
    public function it_shares_the_answer_of_applies_to_between_one_extension_and_the_kind_list(): void
    {
        $registry = ExtensionRegistry::of([], ['products' => [ProductsOnlyExtension::class]], new IlluminateContainer);

        $answers = [
            $registry->appliesTo('products', ProductResource::class),
            $registry->appliesTo('products', CategoryResource::class),
            $registry->appliesTo('missing', ProductResource::class),
            array_keys($registry->applicable(ProductResource::class, NotesResources::class)),
            array_keys($registry->applicable(CategoryResource::class, NotesResources::class)),
        ];

        $this->assertSame([true, false, false, ['products'], []], $answers);
        $this->assertSame([
            ProductsOnlyExtension::class.' '.ProductResource::class,
            ProductsOnlyExtension::class.' '.CategoryResource::class,
        ], ExtensionLog::$asked);
    }

    #[Test]
    public function it_does_not_ask_an_extension_of_another_kind(): void
    {
        $registry = ExtensionRegistry::of([], [], [ProductsOnlyExtension::class], new IlluminateContainer);

        $this->assertSame([], $registry->applicable(ProductResource::class, CountsResources::class));
        $this->assertSame([], ExtensionLog::$asked);
    }

    #[Test]
    public function it_throws_the_first_invalid_entry_when_built(): void
    {
        $registry = ExtensionRegistry::of([], [], [NotingExtension::class, KindlessExtension::class], new IlluminateContainer);

        try {
            $registry->applicable(ProductResource::class, NotesResources::class);
            $this->fail('The kindless extension was built.');
        } catch (InvalidExtensionException $exception) {
            $this->assertSame(
                'The [kindless] extension '.KindlessExtension::class.' implements no extension kind. Implement at least one interface that extends '.ExtensionKind::class.'.',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function it_throws_an_invalid_config_entry_when_read(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::notAClassName(ExtensionList::CONFIG, 'int'));

        ExtensionRegistry::of([NotingExtension::class, 1], [], [], new IlluminateContainer);
    }

    #[Test]
    public function it_throws_an_invalid_disabled_entry_when_read(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::instanceGiven(ExtensionList::DISABLED, NotingExtension::class));

        ExtensionRegistry::of([], [new NotingExtension], [], new IlluminateContainer);
    }

    #[Test]
    public function it_builds_the_class_that_the_name_of_its_class_designates(): void
    {
        $registry = ExtensionRegistry::of([AppAclExtension::class], [AclExtension::class], [AclExtension::class], new IlluminateContainer);

        $this->assertInstanceOf(AppAclExtension::class, $registry->all()['acl']);
        $this->assertSame([AppAclExtension::class], ExtensionLog::$built);
    }
}
