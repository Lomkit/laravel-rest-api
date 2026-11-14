<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;

final class BothKindsExtension extends Extension implements CountsResources, NotesResources
{
    public function __construct()
    {
        ExtensionLog::$built[] = self::class;
    }
}
