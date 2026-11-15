<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

class PageCountMetaExtensionNamedInstructions extends PageCountMetaExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'instructions';
    }
}
