<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;

final class CountingExtension extends Extension implements CountsResources
{
    #[\Override]
    public static function name(): string
    {
        return 'counting';
    }

    public function __construct()
    {
        ExtensionLog::$built[] = self::class;
    }
}
