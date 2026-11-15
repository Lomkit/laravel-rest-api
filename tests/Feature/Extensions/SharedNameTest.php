<?php

namespace Lomkit\Rest\Tests\Feature\Extensions;

use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\RuntimeExtensions;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\FirstAclProvider;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\SecondAclProvider;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;

final class SharedNameTest extends PackageTestCase
{
    #[Before]
    #[After]
    public function resetTheLog(): void
    {
        ExtensionLog::reset();
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [FirstAclProvider::class, SecondAclProvider::class, RestServiceProvider::class];
    }

    #[Test]
    public function it_boots_with_a_name_that_two_packages_register(): void
    {
        $this->assertSame(['acl' => [NotingExtension::class, CountingExtension::class]], $this->app->make(RuntimeExtensions::class)->all());
    }

    #[Test]
    #[WithConfig('rest.extensions', ['acl' => false, 'counting' => CountingExtension::class])]
    public function it_lets_the_config_keep_none_and_rename_one(): void
    {
        $extensions = $this->context()->extensions()->all();

        $this->assertSame(['counting'], array_keys($extensions));
        $this->assertSame([CountingExtension::class], ExtensionLog::$built);
    }

    #[Test]
    #[WithConfig('rest.extensions', ['acl' => NotingExtension::class])]
    public function it_lets_the_config_keep_one_class(): void
    {
        $extensions = $this->context()->extensions()->all();

        $this->assertSame(['acl'], array_keys($extensions));
        $this->assertInstanceOf(NotingExtension::class, $extensions['acl']);
        $this->assertSame([NotingExtension::class], ExtensionLog::$built);
    }

    #[Test]
    public function it_refuses_an_unsettled_name_when_the_registry_is_built(): void
    {
        $context = $this->context();

        $this->expectExceptionObject(InvalidExtensionException::sharedName('acl', [NotingExtension::class, CountingExtension::class]));

        $context->extensions();
    }

    private function context(): RestContext
    {
        return new RestContext(RestRequest::create('/api/products/search', 'POST'), $this->app);
    }
}
