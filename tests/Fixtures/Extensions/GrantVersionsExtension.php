<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Authorization\RowVersions;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\VersionsRowChecks;

final class GrantVersionsExtension extends Extension implements VersionsRowChecks
{
    #[\Override]
    public static function name(): string
    {
        return 'grants';
    }

    public function __construct()
    {
        ExtensionLog::$built[] = self::class;
    }

    #[\Override]
    public function versionRowChecks(RowVersions $versions): void {}
}
