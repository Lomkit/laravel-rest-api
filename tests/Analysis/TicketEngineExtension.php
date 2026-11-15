<?php

namespace Lomkit\Rest\Tests\Analysis;

use Lomkit\Rest\Extensions\Contexts\EngineProfileContext;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresIndexAttributes;
use Lomkit\Rest\Extensions\Kinds\ProvidesEngineProfiles;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\StringListAttribute;

final class TicketEngineExtension extends Extension implements DeclaresIndexAttributes, ProvidesEngineProfiles, RestrictsEngineQueries
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === TicketDeskResource::class;
    }

    #[\Override]
    public function indexAttributes(IndexAttributesContext $context): array
    {
        $attributes = [Integer::make('team_id')];

        if ($context->matchesStringLists) {
            $attributes[] = StringListAttribute::make('readers');
        }

        return $attributes;
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        if ($context->rowsRestrictedInSql) {
            return;
        }

        $user = $context->user;

        if ($user === null) {
            $context->query->matchNothing();

            return;
        }

        $context->query->where('team_id', '=', $user->getAuthIdentifier())->whereNotIn('team_id', [0]);

        $reader = $user->getAuthIdentifier();

        if ($context->matchesStringLists && is_string($reader)) {
            $context->query->whereIn('readers', [$reader]);
        }
    }

    #[\Override]
    public function engineProfile(EngineProfileContext $context): ?EngineProfile
    {
        return null;
    }
}
