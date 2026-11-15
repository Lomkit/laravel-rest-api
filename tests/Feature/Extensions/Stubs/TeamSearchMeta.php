<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;

final class TeamSearchMeta extends Extension implements AddsSearchMeta
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === TeamResource::class;
    }

    #[\Override]
    public function searchMeta(SearchMetaContext $context): array
    {
        return ['teams' => $context->total];
    }
}
