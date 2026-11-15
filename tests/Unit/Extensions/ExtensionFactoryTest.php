<?php

namespace Lomkit\Rest\Tests\Unit\Extensions;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\ExtensionFactory;
use Lomkit\Rest\Tests\Fixtures\Extensions\AbstractNotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\InjectedExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\KindlessExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\MarkerOnlyExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotAnExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotesResources;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\UnresolvableExtension;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExtensionFactoryTest extends TestCase
{
    private const string MISSING = 'Lomkit\Rest\Tests\Fixtures\Extensions\MissingExtension';

    #[Before]
    #[After]
    public function resetTheLog(): void
    {
        ExtensionLog::reset();
    }

    #[Test]
    public function it_builds_an_extension_with_the_services_of_its_constructor(): void
    {
        $container = new Container;
        $config = new ConfigRepository(['app' => ['name' => 'Shop']]);
        $container->instance(Repository::class, $config);

        $extension = (new ExtensionFactory($container))->make(InjectedExtension::class);

        $this->assertInstanceOf(InjectedExtension::class, $extension);
        $this->assertSame($config, $extension->config);
        $this->assertSame([InjectedExtension::class], ExtensionLog::$built);
    }

    #[Test]
    public function it_refuses_a_class_that_does_not_exist(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::notAClass(self::MISSING));

        $this->make(self::MISSING);
    }

    #[Test]
    public function it_refuses_a_kind_interface_given_as_the_class(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::notAClass(NotesResources::class));

        $this->make(NotesResources::class);
    }

    #[Test]
    public function it_refuses_a_class_that_does_not_extend_extension(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::notAnExtension(NotAnExtension::class));

        $this->make(NotAnExtension::class);
    }

    #[Test]
    public function it_refuses_the_extension_base_class(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::notAnExtension(Extension::class));

        $this->make(Extension::class);
    }

    #[Test]
    public function it_refuses_an_extension_that_implements_no_kind(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::noKind(KindlessExtension::class));

        $this->make(KindlessExtension::class);
    }

    #[Test]
    public function it_refuses_an_extension_that_implements_only_the_kind_marker(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::noKind(MarkerOnlyExtension::class));

        $this->make(MarkerOnlyExtension::class);
    }

    #[Test]
    public function it_refuses_an_abstract_extension(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::notInstantiable(AbstractNotingExtension::class));

        $this->make(AbstractNotingExtension::class);
    }

    #[Test]
    public function it_refuses_a_binding_that_builds_another_class(): void
    {
        $container = new Container;
        $container->bind(NotingExtension::class, fn (): CountingExtension => new CountingExtension);

        $this->expectExceptionObject(InvalidExtensionException::foreignBinding(NotingExtension::class, CountingExtension::class));

        (new ExtensionFactory($container))->make(NotingExtension::class);
    }

    #[Test]
    public function it_lets_an_unresolvable_constructor_fail(): void
    {
        $this->expectException(BindingResolutionException::class);

        $this->make(UnresolvableExtension::class);
    }

    #[Test]
    public function it_names_the_class_in_each_refusal(): void
    {
        $refusals = [
            NotingExtension::class => [
                InvalidExtensionException::lateRegistration(NotingExtension::class),
                InvalidExtensionException::foreignBinding(NotingExtension::class, CountingExtension::class),
                InvalidExtensionException::instanceGiven('Rest::registerExtensions()', NotingExtension::class),
            ],
            self::MISSING => [InvalidExtensionException::notAClass(self::MISSING)],
            NotAnExtension::class => [InvalidExtensionException::notAnExtension(NotAnExtension::class)],
            KindlessExtension::class => [InvalidExtensionException::noKind(KindlessExtension::class), InvalidExtensionException::invalidName(KindlessExtension::class, 'Acl')],
            AbstractNotingExtension::class => [InvalidExtensionException::notInstantiable(AbstractNotingExtension::class)],
        ];

        foreach ($refusals as $class => $exceptions) {
            foreach ($exceptions as $exception) {
                $this->assertTrue($exception->concerns($class));
                $this->assertFalse($exception->concerns(CountingExtension::class));
                $this->assertStringContainsString($class, $exception->getMessage());
            }
        }

        $this->assertFalse(InvalidExtensionException::sharedName('acl', [NotingExtension::class, CountingExtension::class])->concerns(NotingExtension::class));
    }

    private function make(string $class): Extension
    {
        return (new ExtensionFactory(new Container))->make($class);
    }
}
