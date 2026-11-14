<?php

namespace Lomkit\Rest\Tests\Feature\Extensions;

use Lomkit\Rest\Rest;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Fixtures\Extensions\EarlyExtensionProvider;
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
    }

    #[After]
    public function clearTheFacadeRoot(): void
    {
        EarlyExtensionProvider::$root = null;
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [EarlyExtensionProvider::class, RestServiceProvider::class];
    }

    #[Test]
    public function it_resolves_the_facade_from_a_provider_registered_before_the_package(): void
    {
        $this->assertSame($this->app->make(Rest::class), EarlyExtensionProvider::$root);
    }
}
