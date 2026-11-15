<?php

namespace Lomkit\Rest\Tests\Feature\Extensions;

use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\RuntimeExtensions;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Fixtures\Extensions\AclExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\FirstAclProvider;
use Lomkit\Rest\Tests\Fixtures\Extensions\RivalAclExtension;
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
    public function it_boots_with_two_packages_whose_classes_share_a_name(): void
    {
        $this->assertSame([AclExtension::class, RivalAclExtension::class], $this->app->make(RuntimeExtensions::class)->all());
    }

    #[Test]
    #[WithConfig('rest.disabled_extensions', [RivalAclExtension::class])]
    public function it_lets_the_config_disable_one_of_the_classes(): void
    {
        $extensions = $this->context()->extensions()->all();

        $this->assertSame(['acl'], array_keys($extensions));
        $this->assertInstanceOf(AclExtension::class, $extensions['acl']);
        $this->assertSame([AclExtension::class], ExtensionLog::$built);
    }

    #[Test]
    public function it_refuses_two_classes_with_one_name_when_the_registry_is_built(): void
    {
        $context = $this->context();

        try {
            $context->extensions();
            $this->fail('The shared name was accepted.');
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::sharedName('acl', [AclExtension::class, RivalAclExtension::class]), $exception);
            $this->assertSame(
                'The extensions '.AclExtension::class.' and '.RivalAclExtension::class.' share the name [acl].'
                .' Add one of them to the [rest.disabled_extensions] config, or extend one of them and override name() to give it another name.',
                $exception->getMessage(),
            );
            $this->assertSame([], ExtensionLog::$built);
        }
    }

    private function context(): RestContext
    {
        return new RestContext(RestRequest::create('/api/products/search', 'POST'), $this->app);
    }
}
