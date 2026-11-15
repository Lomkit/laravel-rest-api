<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Closure;
use Lomkit\Rest\Authorization\RowVersions;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\VersionLog;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;

class VersionedTicketResource extends CachedTicketResource
{
    /** @var list<RowVersions> */
    public static array $received = [];

    /** @var (Closure(RowVersions): void)|null */
    public static ?Closure $version = null;

    #[\Override]
    public function versionAuthorization(RowVersions $versions): void
    {
        self::$received[] = $versions;
        VersionLog::record('hook', $versions);

        (self::$version ?? TeamVersions::add(...))($versions);
    }
}
