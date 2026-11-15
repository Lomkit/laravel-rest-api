<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\DetailsMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsDetailsMeta;

class RegionDetailsMeta extends Extension implements AddsDetailsMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'region';
    }

    #[\Override]
    public function detailsMeta(DetailsMetaContext $context): array
    {
        return [];
    }
}
