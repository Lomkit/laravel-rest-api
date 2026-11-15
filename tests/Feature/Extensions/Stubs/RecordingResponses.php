<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\MetaDocumentationContext;
use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DocumentsExtension;

final class RecordingResponses extends Extension implements DocumentsExtension
{
    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void
    {
        MetaLog::$operations[] = $context;
    }

    #[\Override]
    public function documentMeta(MetaDocumentationContext $context): void {}
}
