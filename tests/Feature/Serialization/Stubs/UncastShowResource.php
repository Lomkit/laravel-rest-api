<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;

class UncastShowResource extends ShowResource
{
    public static string $model = UncastShow::class;
}
