<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Authorization\RowVersions;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\VersionedTagResource;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\VersionedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\RecordingPolicy;

final class VersionLog
{
    public static function record(string $tag, RowVersions $versions): void
    {
        $policy = Gate::getPolicyFor($versions->rows->firstOrFail());

        if ($policy instanceof RecordingPolicy) {
            $user = $versions->user?->getAuthIdentifier() ?? 'guest';
            $policy->calls[] = 'versions '.$tag.' '.$versions->ability.' '.implode(',', $versions->rows->modelKeys()).' for '.$user;
        }
    }

    public static function reset(): void
    {
        VersionedTicketResource::$received = [];
        VersionedTicketResource::$version = null;
        VersionedTagResource::$groups = [];
    }
}
