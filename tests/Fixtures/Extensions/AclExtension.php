<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;

class AclExtension extends Extension implements NotesResources
{
    #[\Override]
    public static function name(): string
    {
        return 'acl';
    }

    public function __construct()
    {
        ExtensionLog::$built[] = static::class;
    }
}
