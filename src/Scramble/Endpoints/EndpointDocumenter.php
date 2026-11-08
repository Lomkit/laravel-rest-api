<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Dedoc\Scramble\Support\Generator\Operation;
use Lomkit\Rest\Scramble\DocumentationContext;

interface EndpointDocumenter
{
    public function document(Operation $operation, DocumentationContext $context): void;
}
