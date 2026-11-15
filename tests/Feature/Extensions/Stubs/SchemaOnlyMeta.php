<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Lomkit\Rest\Extensions\Contexts\MetaDocumentationContext;
use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DocumentsExtension;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;

final class SchemaOnlyMeta extends Extension implements DocumentsExtension
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === StageResource::class;
    }

    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void {}

    #[\Override]
    public function documentMeta(MetaDocumentationContext $context): void
    {
        $context->searchMeta(new ObjectType);
    }
}
