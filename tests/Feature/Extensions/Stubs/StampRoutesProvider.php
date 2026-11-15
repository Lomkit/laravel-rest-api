<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Support\ServiceProvider;
use Lomkit\Rest\Facades\Rest;

final class StampRoutesProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        Rest::registerExtension('stamp', StampRoutes::class);
    }
}
