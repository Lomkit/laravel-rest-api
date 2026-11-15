<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Illuminate\Support\ServiceProvider;
use Lomkit\Rest\Facades\Rest;

final class LaterExtensionProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        Rest::registerExtension(BothKindsExtension::class);
    }
}
