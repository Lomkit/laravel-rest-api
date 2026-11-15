<?php

namespace Lomkit\Rest\Scramble\Components;

use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Lomkit\Rest\Exceptions\InvalidDocumentationExtensionException;
use Lomkit\Rest\Extensions\Contexts\MetaDocumentationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsDetailsMeta;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;
use Lomkit\Rest\Extensions\Kinds\DocumentsExtension;
use Lomkit\Rest\Http\Details\DetailsMeta;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Search\Run\ExtensionSearchMeta;
use Lomkit\Rest\Support\Overrides;

/** @internal */
final readonly class MetaSchema
{
    /** @return array<string, ObjectType> */
    public static function search(Resource $resource): array
    {
        $extensions = ExtensionSearchMeta::extensions($resource);
        $records = self::records($resource);
        $properties = [];

        foreach (array_keys($extensions) as $name) {
            $properties[$name] = self::described($records[$name]->search ?? null, "Added by the [{$name}] extension for the page");
        }

        return $properties;
    }

    public static function details(Resource $resource): ObjectType
    {
        $properties = self::detailsProperties($resource);

        if (! Overrides::method($resource, 'detailsMeta', Resource::class)) {
            return $properties === []
                ? Schemas::closed([], description: 'Empty: the resource adds no keys with detailsMeta()')
                : Schemas::closed($properties, array_keys($properties), 'The keys that extensions add, each under the name of the extension, for the user of the request');
        }

        $meta = Schemas::object($properties);
        $meta->setRequired(array_keys($properties));
        $meta->additionalProperties(new MixedType);
        $meta->setDescription($properties === []
            ? 'The keys that detailsMeta() of the resource returns for the user of the request'
            : 'The keys that detailsMeta() of the resource returns for the user of the request, then the keys that extensions add, each under the name of the extension');

        return $meta;
    }

    /** @return array<string, ObjectType> */
    private static function detailsProperties(Resource $resource): array
    {
        $extensions = DetailsMeta::extensions($resource);
        $records = self::records($resource);
        $properties = [];

        foreach (array_keys($extensions) as $name) {
            $properties[$name] = self::described($records[$name]->details ?? null, "Added by the [{$name}] extension for the user of the request");
        }

        return $properties;
    }

    /** @return array<string, MetaSchemaRecord> */
    private static function records(Resource $resource): array
    {
        $registry = $resource->context()->extensions();
        $extensions = $registry->applicable($resource::class, DocumentsExtension::class);
        $records = [];

        foreach ($extensions as $name => $extension) {
            $record = new MetaSchemaRecord;
            $extension->documentMeta(new MetaDocumentationContext($resource::class, $record));
            self::ensureAdded($record->search, $name, $registry->classOf($name), $extension, $resource, AddsSearchMeta::class);
            self::ensureAdded($record->details, $name, $registry->classOf($name), $extension, $resource, AddsDetailsMeta::class);
            $records[$name] = $record;
        }

        return $records;
    }

    /** @param class-string<AddsSearchMeta|AddsDetailsMeta> $kind */
    private static function ensureAdded(?ObjectType $schema, string $name, string $class, Extension $extension, Resource $resource, string $kind): void
    {
        if ($schema !== null && ! $extension instanceof $kind) {
            throw InvalidDocumentationExtensionException::metaWithoutKind($name, $class, $resource::class, $kind);
        }
    }

    private static function described(?ObjectType $given, string $description): ObjectType
    {
        if ($given === null) {
            $open = new ObjectType;
            $open->additionalProperties(new MixedType);

            return $open->setDescription($description);
        }

        $schema = clone $given;

        return $schema->description === '' ? $schema->setDescription($description) : $schema;
    }
}
