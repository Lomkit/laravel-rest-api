<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PrefixedVoucherResource;

final class NamePrefixesRestriction extends Extension implements RestrictsEngineQueries
{
    #[\Override]
    public static function name(): string
    {
        return 'prefixes';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return is_a($resource, PrefixedVoucherResource::class, true);
    }

    #[\Override]
    public function restrictEngine(EngineRestrictionContext $context): void
    {
        $context->query->whereIn('name_prefixes', ['re', 'bu']);
    }
}
