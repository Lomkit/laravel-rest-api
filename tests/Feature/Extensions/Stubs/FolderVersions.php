<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Lomkit\Rest\Authorization\RowVersions;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\VersionsRowChecks;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\TeamVersions;

class FolderVersions extends Extension implements VersionsRowChecks
{
    #[\Override]
    public static function name(): string
    {
        return 'folder';
    }

    /** @var list<class-string<Resource>>|null */
    public static ?array $resources = null;

    /** @var list<RowVersions> */
    public static array $received = [];

    /** @var (Closure(RowVersions): void)|null */
    public static ?Closure $version = null;

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return self::$resources === null || in_array($resource, self::$resources, true);
    }

    #[\Override]
    public function versionRowChecks(RowVersions $versions): void
    {
        self::$received[] = $versions;
        VersionLog::record(static::name(), $versions);

        (self::$version ?? TeamVersions::add(...))($versions);
    }
}
