<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Dedoc\Scramble\ScrambleServiceProvider;
use Lomkit\Rest\RestServiceProvider;

class PackageFirstSnapshotTest extends SnapshotTest
{
    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [RestServiceProvider::class, ScrambleServiceProvider::class];
    }
}
