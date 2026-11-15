<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Lomkit\Rest\Authorization\RowVersions;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\VersionsRowChecks;

class SecondFolderVersions extends Extension implements VersionsRowChecks
{
    #[\Override]
    public static function name(): string
    {
        return 'a';
    }

    /** @var list<RowVersions> */
    public static array $received = [];

    /** @var (Closure(RowVersions): void)|null */
    public static ?Closure $version = null;

    #[\Override]
    public function versionRowChecks(RowVersions $versions): void
    {
        self::$received[] = $versions;
        VersionLog::record('a', $versions);

        if (self::$version !== null) {
            (self::$version)($versions);
        }
    }
}
