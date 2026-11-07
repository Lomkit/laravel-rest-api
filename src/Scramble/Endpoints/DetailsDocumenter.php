<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Dedoc\Scramble\Support\Generator\Operation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\DetailsResponseType;

final readonly class DetailsDocumenter implements EndpointDocumenter
{
    #[\Override]
    public function document(Operation $operation, Resource $resource, string $name): void
    {
        DetailsResponseType::document($operation, $name);
    }
}
