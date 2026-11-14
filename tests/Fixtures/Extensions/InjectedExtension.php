<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Illuminate\Contracts\Config\Repository;
use Lomkit\Rest\Extensions\Extension;

final class InjectedExtension extends Extension implements NotesResources
{
    public function __construct(public readonly Repository $config)
    {
        ExtensionLog::$built[] = self::class;
    }
}
