<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Dedoc\Scramble\Support\Generator\Operation;
use Lomkit\Rest\Http\Resource;

interface EndpointDocumenter
{
    public function document(Operation $operation, Resource $resource, string $name): void;
}
