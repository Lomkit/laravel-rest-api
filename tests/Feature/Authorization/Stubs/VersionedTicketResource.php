<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Closure;
use Lomkit\Rest\Authorization\RowVersions;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\VersionLog;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;

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

    #[\Override]
    public function relations(): array
    {
        return [
            BelongsTo::make('team', TeamResource::class),
            BelongsToMany::make('tags', VersionedTagResource::class)->abilities(attach: 'attachTag', detach: 'detachTag'),
        ];
    }
}
