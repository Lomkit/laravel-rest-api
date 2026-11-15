<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;

final class ShelvesExtension extends Extension implements NotesResources
{
    #[\Override]
    public static function name(): string
    {
        return 'aisles';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        ExtensionLog::$asked[] = self::class.' '.$resource;

        return true;
    }
}
