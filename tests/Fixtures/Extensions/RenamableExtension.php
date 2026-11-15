<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;

final class RenamableExtension extends Extension implements NotesResources
{
    public const string NAME = 'renamable';

    public static string $given = self::NAME;

    #[\Override]
    public static function name(): string
    {
        ExtensionLog::$named[] = self::class;

        return self::$given;
    }
}
