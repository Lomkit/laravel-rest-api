<?php

namespace Lomkit\Rest\Tests\Feature\Extensions;

use Lomkit\Rest\Extensions\RuntimeExtensions;
use Lomkit\Rest\Rest;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Fixtures\Extensions\BootingExtensionProvider;
use Lomkit\Rest\Tests\Fixtures\Extensions\BothKindsExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\EarlyExtensionProvider;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\LaterExtensionProvider;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;

final class ProviderOrderTest extends PackageTestCase
{
    #[Before]
    public function forgetTheFacadeRoot(): void
    {
        EarlyExtensionProvider::$root = null;
        ExtensionLog::reset();
    }

    #[After]
    public function clearTheFacadeRoot(): void
    {
        EarlyExtensionProvider::$root = null;
        ExtensionLog::reset();
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [EarlyExtensionProvider::class, BootingExtensionProvider::class, RestServiceProvider::class, LaterExtensionProvider::class];
    }

    #[Test]
    public function it_resolves_the_facade_from_a_provider_registered_before_the_package(): void
    {
        $this->assertSame($this->app->make(Rest::class), EarlyExtensionProvider::$root);
    }

    #[Test]
    public function it_keeps_one_store_for_every_provider(): void
    {
        $store = $this->app->make(RuntimeExtensions::class);

        $this->assertSame($store, $this->app->make(RuntimeExtensions::class));
        $this->assertSame([
            'early' => NotingExtension::class,
            'later' => BothKindsExtension::class,
            'booted' => CountingExtension::class,
        ], $store->all());
    }
}
