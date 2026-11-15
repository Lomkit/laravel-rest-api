<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Extensions\Applicability;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\PreparedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\RecordingPolicy;

final class RowCheckLog
{
    public static function record(string $tag, PendingChecks $checks): void
    {
        $policy = Gate::getPolicyFor($checks->rows->firstOrFail());

        if ($policy instanceof RecordingPolicy) {
            $user = $checks->user?->getAuthIdentifier() ?? 'guest';
            $policy->calls[] = 'ext '.$tag.' '.$checks->ability.' '.implode(',', $checks->rows->modelKeys()).' for '.$user;
        }
    }

    public static function reset(): void
    {
        PreparingExtension::$resources = null;
        PreparingExtension::$received = [];
        PreparingExtension::$prepare = null;
        SecondPreparingExtension::$received = [];
        SecondPreparingExtension::$prepare = null;
        PreparedTicketResource::$prepare = null;
        AugmentedPreparingExtension::$alias = null;
        AugmentedPreparingExtension::$gates = [];
        AugmentedPreparingExtension::$received = [];
        Applicability::flush();
    }
}
