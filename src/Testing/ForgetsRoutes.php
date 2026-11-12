<?php

namespace Lomkit\Rest\Testing;

use Lomkit\Rest\Http\Routing\RememberedRoutes;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait ForgetsRoutes
{
    #[Before]
    public function forgetRoutesBeforeTheApplicationBoots(): void
    {
        RememberedRoutes::forget();
    }

    #[After]
    public function forgetRoutesAfterTheTest(): void
    {
        RememberedRoutes::forget();
    }
}
