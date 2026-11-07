<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\Schemas;

final readonly class KeysDocumenter implements EndpointDocumenter
{
    public function __construct(
        private string $verb,
    ) {}

    #[\Override]
    public function document(Operation $operation, Resource $resource, string $name): void
    {
        $operation->summary("{$this->verb} {$name}");

        Schemas::requestBody($operation, Schemas::object(['resources' => Schemas::list(new IntegerType)]));
    }
}
