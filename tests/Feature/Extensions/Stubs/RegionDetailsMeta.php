<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\DetailsMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsDetailsMeta;

final class RegionDetailsMeta extends Extension implements AddsDetailsMeta
{
    #[\Override]
    public function detailsMeta(DetailsMetaContext $context): array
    {
        return [];
    }
}
