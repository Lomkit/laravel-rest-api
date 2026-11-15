<?php

namespace Lomkit\Rest\Tests\Feature\Extensions;

use Lomkit\Rest\Extensions\RuntimeExtensions;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Rest;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
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
    use ResetsExtensionStubs;

    #[Before]
    public function forgetTheFacadeRoot(): void
    {
        EarlyExtensionProvider::$root = null;
    }

    #[After]
    public function clearTheFacadeRoot(): void
    {
        EarlyExtensionProvider::$root = null;
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
    public function it_accepts_registrations_whatever_the_provider_order(): void
    {
        $store = $this->app->make(RuntimeExtensions::class);
        $context = new RestContext(RestRequest::create('/api/products/search', 'POST'), $this->app);

        $this->assertSame($store, $this->app->make(RuntimeExtensions::class));
        $this->assertSame([NotingExtension::class, BothKindsExtension::class, CountingExtension::class], $store->all());

        $this->assertSame(['noting', 'both', 'counting'], array_keys($context->extensions()->all()));
        $this->assertSame([NotingExtension::class, BothKindsExtension::class, CountingExtension::class], ExtensionLog::$built);
    }
}
