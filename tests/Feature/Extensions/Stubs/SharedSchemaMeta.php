<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Lomkit\Rest\Extensions\Contexts\DetailsMetaContext;
use Lomkit\Rest\Extensions\Contexts\MetaDocumentationContext;
use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsDetailsMeta;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;
use Lomkit\Rest\Extensions\Kinds\DocumentsExtension;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;

final class SharedSchemaMeta extends Extension implements AddsDetailsMeta, AddsSearchMeta, DocumentsExtension
{
    public static ?ObjectType $schema = null;

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === StageResource::class;
    }

    #[\Override]
    public function searchMeta(SearchMetaContext $context): array
    {
        return ['count' => 1];
    }

    #[\Override]
    public function detailsMeta(DetailsMetaContext $context): array
    {
        return ['count' => 1];
    }

    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void {}

    #[\Override]
    public function documentMeta(MetaDocumentationContext $context): void
    {
        self::$schema ??= Schemas::required(['count' => new IntegerType]);
        $context->searchMeta(self::$schema);
        $context->detailsMeta(self::$schema);
    }
}
