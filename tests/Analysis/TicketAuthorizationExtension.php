<?php

namespace Lomkit\Rest\Tests\Analysis;

use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Authorization\RowVersions;
use Lomkit\Rest\Extensions\Contexts\CacheContributionContext;
use Lomkit\Rest\Extensions\Contexts\GatesContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ContributesCacheContext;
use Lomkit\Rest\Extensions\Kinds\DeclaresGates;
use Lomkit\Rest\Extensions\Kinds\PreparesRowChecks;
use Lomkit\Rest\Extensions\Kinds\VersionsRowChecks;

final class TicketAuthorizationExtension extends Extension implements ContributesCacheContext, DeclaresGates, PreparesRowChecks, VersionsRowChecks
{
    #[\Override]
    public static function name(): string
    {
        return 'ticket_authorization';
    }

    /** @var array<string, list<int|string>> */
    private array $prepared = [];

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === TicketDeskResource::class;
    }

    #[\Override]
    public function prepareRowChecks(PendingChecks $checks): void
    {
        $user = $checks->user?->getAuthIdentifier();
        $key = $checks->resource::class.'|'.$checks->ability.'|'.(is_scalar($user) ? (string) $user : 'guest');
        $this->prepared[$key] = array_values($checks->rows->modelKeys());
    }

    #[\Override]
    public function versionRowChecks(RowVersions $versions): void
    {
        foreach ($versions->rows as $row) {
            $versions->add($row, 'grants', $versions->user === null ? 'guest' : 'v1');
        }
    }

    /** @return list<int|string> */
    public function prepared(string $key): array
    {
        return $this->prepared[$key] ?? [];
    }

    #[\Override]
    public function contributeCacheContext(CacheContributionContext $context): void
    {
        $user = $context->user?->getAuthIdentifier();

        $context->put('tenant', is_int($user) ? $user : null);
        $context->put('rules', 'v1');
        $context->shorten('view', 60);
        $context->forget('update');
    }

    #[\Override]
    public function gates(GatesContext $context): array
    {
        return $context->resource === TicketDeskResource::class ? ['share'] : [];
    }
}
