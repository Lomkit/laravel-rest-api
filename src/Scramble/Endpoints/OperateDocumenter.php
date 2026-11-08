<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Dedoc\Scramble\Support\Generator\Operation;
use Lomkit\Rest\Scramble\DocumentationContext;

final readonly class OperateDocumenter implements EndpointDocumenter
{
    #[\Override]
    public function document(Operation $operation, DocumentationContext $context): void
    {
        $declaration = $context->declaration();

        if ($declaration === null) {
            return;
        }

        $operation->summary($declaration->name());
    }
}
