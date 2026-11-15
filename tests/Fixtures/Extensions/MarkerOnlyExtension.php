<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ExtensionKind;

final class MarkerOnlyExtension extends Extension implements ExtensionKind
{
    #[\Override]
    public static function name(): string
    {
        return 'marker';
    }
}
