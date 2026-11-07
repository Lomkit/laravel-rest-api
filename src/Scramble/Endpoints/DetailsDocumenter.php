<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Dedoc\Scramble\Support\Generator\Operation;
use Lomkit\Rest\Scramble\DetailsResponseType;
use Lomkit\Rest\Scramble\DocumentationContext;

final readonly class DetailsDocumenter implements EndpointDocumenter
{
    #[\Override]
    public function document(Operation $operation, DocumentationContext $context): void
    {
        DetailsResponseType::document($operation, $context->name);
    }
}
