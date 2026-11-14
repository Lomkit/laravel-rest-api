<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Illuminate\Support\ServiceProvider;
use Lomkit\Rest\Facades\Rest;

final class BootingExtensionProvider extends ServiceProvider
{
    public function boot(): void
    {
        Rest::registerExtensions(['booted' => CountingExtension::class]);
    }
}
