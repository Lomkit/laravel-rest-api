<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Authorization\RowVersions;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\VersionLog;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;

class VersionedTagResource extends TagResource
{
    /** @var array<int, int> */
    public static array $groups = [];

    #[\Override]
    public function versionAuthorization(RowVersions $versions): void
    {
        VersionLog::record('tag', $versions);

        foreach ($versions->rows as $tag) {
            $versions->add($tag, 'group', self::$groups[$tag->getKey()] ?? 0);
        }
    }
}
