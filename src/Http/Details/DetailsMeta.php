<?php

namespace Lomkit\Rest\Http\Details;

use Lomkit\Rest\Extensions\Contexts\DetailsMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsDetailsMeta;
use Lomkit\Rest\Http\ExtensionMetaValues;
use Lomkit\Rest\Http\Resource;
use stdClass;

/** @internal */
final readonly class DetailsMeta
{
    private const string METHOD = 'detailsMeta';

    /** @return array<string, Extension&AddsDetailsMeta> */
    public static function extensions(Resource $resource): array
    {
        $extensions = $resource->context()->extensions()->applicable($resource::class, AddsDetailsMeta::class);
        ksort($extensions);

        return $extensions;
    }

    public static function of(Resource $resource): stdClass
    {
        $own = $resource->detailsMeta();
        $extensions = self::extensions($resource);

        if ($extensions === []) {
            return (object) $own;
        }

        $user = $resource->context()->actingUser()->user;
        $added = ExtensionMetaValues::collect($resource, self::METHOD, $own, $extensions, fn (Extension&AddsDetailsMeta $extension): array => $extension->detailsMeta(
            new DetailsMetaContext($resource, $user),
        ));

        return (object) ($own + $added);
    }
}
