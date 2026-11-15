<?php

namespace Lomkit\Rest\Search\Run;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidMetaExtensionException;
use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;
use Lomkit\Rest\Http\ExtensionMetaValues;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RowCopies;
use Lomkit\Rest\Search\SearchContext;
use stdClass;

/** @internal */
final readonly class ExtensionSearchMeta
{
    private const string METHOD = 'searchMeta';

    /** @return array<string, Extension&AddsSearchMeta> */
    public static function extensions(Resource $resource): array
    {
        $extensions = $resource->context()->extensions()->applicable($resource::class, AddsSearchMeta::class);

        if ($extensions === []) {
            return [];
        }

        ksort($extensions);
        $gatesKey = $resource->context()->config()->gatesKey;

        foreach ($extensions as $name => $extension) {
            if ($name === SearchMeta::INSTRUCTIONS) {
                throw InvalidMetaExtensionException::instructionsName($name, $extension::class, $resource::class);
            }

            if ($name === $gatesKey) {
                throw InvalidMetaExtensionException::gatesKeyName($name, $extension::class, $resource::class);
            }
        }

        return $extensions;
    }

    /**
     * @param  LengthAwarePaginator<int, Model>  $page
     * @param  array<string, mixed>  $own
     * @return array<string, stdClass>
     */
    public static function of(Resource $resource, SearchContext $search, LengthAwarePaginator $page, array $own): array
    {
        $extensions = self::extensions($resource);

        if ($extensions === []) {
            return [];
        }

        $user = $resource->context()->actingUser()->user;

        return ExtensionMetaValues::collect($resource, self::METHOD, $own, $extensions, fn (Extension&AddsSearchMeta $extension): array => $extension->searchMeta(
            new SearchMetaContext($resource, $user, RowCopies::of($page->items()), $page->total(), $page->currentPage(), $page->perPage(), $search),
        ));
    }
}
