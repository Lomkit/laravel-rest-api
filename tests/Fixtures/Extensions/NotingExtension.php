<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;

final class NotingExtension extends Extension implements NotesResources
{
    #[\Override]
    public static function name(): string
    {
        return 'noting';
    }

    public function __construct()
    {
        ExtensionLog::$built[] = self::class;
    }
}
