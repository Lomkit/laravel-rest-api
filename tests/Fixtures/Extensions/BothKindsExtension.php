<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;

final class BothKindsExtension extends Extension implements CountsResources, NotesResources
{
    #[\Override]
    public static function name(): string
    {
        return 'both';
    }

    public function __construct()
    {
        ExtensionLog::$built[] = self::class;
    }
}
