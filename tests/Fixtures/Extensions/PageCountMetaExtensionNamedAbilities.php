<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

final class PageCountMetaExtensionNamedAbilities extends PageCountMetaExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'abilities';
    }
}
