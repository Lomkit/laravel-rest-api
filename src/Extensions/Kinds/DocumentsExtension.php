<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\MetaDocumentationContext;
use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;

interface DocumentsExtension extends ExtensionKind
{
    public function documentOperation(OperationDocumentationContext $context): void;

    public function documentMeta(MetaDocumentationContext $context): void;
}
