<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;

final class BadlyNamedExtension extends Extension implements NotesResources
{
    #[\Override]
    public static function name(): string
    {
        return 'Acl';
    }
}
