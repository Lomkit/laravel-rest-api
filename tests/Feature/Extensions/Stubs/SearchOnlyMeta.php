<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Extensions\Contexts\MetaDocumentationContext;
use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;
use Lomkit\Rest\Extensions\Kinds\DocumentsExtension;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;

final class SearchOnlyMeta extends Extension implements AddsSearchMeta, DocumentsExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'search';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === StageResource::class;
    }

    #[\Override]
    public function searchMeta(SearchMetaContext $context): array
    {
        return ['remaining' => 3];
    }

    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void {}

    #[\Override]
    public function documentMeta(MetaDocumentationContext $context): void
    {
        $context->searchMeta(Schemas::closed(['remaining' => new IntegerType], ['remaining']));
        $context->detailsMeta(Schemas::closed(['plan' => new StringType], ['plan']));
    }
}
