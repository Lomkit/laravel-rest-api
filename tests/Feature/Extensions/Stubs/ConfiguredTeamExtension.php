<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Contracts\Config\Repository;
use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;

final class ConfiguredTeamExtension extends Extension implements RestrictsQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'team';
    }

    public function __construct(private readonly Repository $config) {}

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        $context->query->where($context->query->qualifyColumn('team_id'), $this->config->integer('extension_fixtures.team'));
    }
}
