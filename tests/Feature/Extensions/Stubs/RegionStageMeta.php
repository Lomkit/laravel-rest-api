<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\DetailsMetaContext;
use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsDetailsMeta;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;

class RegionStageMeta extends Extension implements AddsDetailsMeta, AddsSearchMeta
{
    public static ?string $named = null;

    #[\Override]
    public static function name(): string
    {
        return self::$named ?? 'region';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === StageResource::class;
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
