<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\DetailsMetaContext;
use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsDetailsMeta;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;

class RegionMeta extends Extension implements AddsDetailsMeta, AddsSearchMeta
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

    #[\Override]
    public function detailsMeta(DetailsMetaContext $context): array
    {
        return [];
    }
}
