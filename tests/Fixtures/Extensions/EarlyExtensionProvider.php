<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Illuminate\Support\ServiceProvider;
use Lomkit\Rest\Facades\Rest;

final class EarlyExtensionProvider extends ServiceProvider
{
    public static ?object $root = null;

    #[\Override]
    public function register(): void
    {
        self::$root = Rest::getFacadeRoot();
    }
}
