<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;

final class KindlessExtension extends Extension
{
    #[\Override]
    public static function name(): string
    {
        return 'kindless';
    }
}
