<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;

class RegionSearchMeta extends Extension implements AddsSearchMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'region';
    }

    #[\Override]
    public function searchMeta(SearchMetaContext $context): array
    {
        return [];
    }
}
