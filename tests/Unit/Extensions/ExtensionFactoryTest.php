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

        $extension = (new ExtensionFactory($container))->make('injected', InjectedExtension::class);

        $this->assertInstanceOf(InjectedExtension::class, $extension);
        $this->assertSame($config, $extension->config);
        $this->assertSame([InjectedExtension::class], ExtensionLog::$built);
    }

    #[Test]
    public function it_refuses_a_class_that_does_not_exist(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::notAClass('acl', self::MISSING));

        $this->make(self::MISSING);
    }

    #[Test]
    public function it_refuses_a_kind_interface_given_as_the_class(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::notAClass('acl', NotesResources::class));

        $this->make(NotesResources::class);
    }

    #[Test]
    public function it_refuses_a_class_that_does_not_extend_extension(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::notAnExtension('acl', NotAnExtension::class));

        $this->make(NotAnExtension::class);
    }

    #[Test]
    public function it_refuses_the_extension_base_class(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::notAnExtension('acl', Extension::class));

        $this->make(Extension::class);
    }

    #[Test]
    public function it_refuses_an_extension_that_implements_no_kind(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::noKind('acl', KindlessExtension::class));

        $this->make(KindlessExtension::class);
    }

    #[Test]
    public function it_refuses_an_extension_that_implements_only_the_kind_marker(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::noKind('acl', MarkerOnlyExtension::class));

        $this->make(MarkerOnlyExtension::class);
    }

    #[Test]
    public function it_refuses_an_abstract_extension(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::notInstantiable('acl', AbstractNotingExtension::class));

        $this->make(AbstractNotingExtension::class);
    }

    #[Test]
    public function it_refuses_a_binding_that_builds_another_class(): void
    {
        $container = new Container;
        $container->bind(NotingExtension::class, fn (): CountingExtension => new CountingExtension);

        $this->expectExceptionObject(InvalidExtensionException::foreignBinding('acl', NotingExtension::class, CountingExtension::class));

        (new ExtensionFactory($container))->make('acl', NotingExtension::class);
    }

    #[Test]
    public function it_lets_an_unresolvable_constructor_fail(): void
    {
        $this->expectException(BindingResolutionException::class);

        $this->make(UnresolvableExtension::class);
    }

    #[Test]
    public function it_names_the_extension_in_each_refusal(): void
    {
        $refusals = [
            InvalidExtensionException::invalidName('acl'),
            InvalidExtensionException::duplicateName('acl', NotingExtension::class, CountingExtension::class),
            InvalidExtensionException::repeatedRegistration('acl', NotingExtension::class),
            InvalidExtensionException::lateRegistration('acl', NotingExtension::class),
            InvalidExtensionException::notAClass('acl', self::MISSING),
            InvalidExtensionException::notAnExtension('acl', NotAnExtension::class),
            InvalidExtensionException::noKind('acl', KindlessExtension::class),
            InvalidExtensionException::notInstantiable('acl', AbstractNotingExtension::class),
            InvalidExtensionException::foreignBinding('acl', NotingExtension::class, CountingExtension::class),
        ];

        foreach ($refusals as $refusal) {
            $this->assertTrue($refusal->namesExtension('acl'));
            $this->assertFalse($refusal->namesExtension('tenant'));
        }

        $this->assertFalse(InvalidExtensionException::missingName(NotingExtension::class)->namesExtension('acl'));
    }

    private function make(string $class): Extension
    {
        return (new ExtensionFactory(new Container))->make('acl', $class);
    }
}
