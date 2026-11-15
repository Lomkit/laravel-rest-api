<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class LateFolderVersions extends FolderVersions
{
    #[\Override]
    public static function name(): string
    {
        return 'b';
    }
}
