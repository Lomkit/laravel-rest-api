<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Dedoc\Scramble\Support\Generator\Operation;
use Lomkit\Rest\Scramble\DocumentationContext;
use Lomkit\Rest\Scramble\Mutate\MutationExample;
use Lomkit\Rest\Scramble\Mutate\MutationSchema;
use Lomkit\Rest\Scramble\Mutate\ResultSchema;
use Lomkit\Rest\Scramble\Schemas;

final readonly class MutateDocumenter implements EndpointDocumenter
{
    #[\Override]
    public function document(Operation $operation, DocumentationContext $context): void
    {
        $operation->summary("Mutate {$context->name}");

        Schemas::requestBody(
            $operation,
            (new MutationSchema($context->components))->body($context->resource),
            ['default' => MutationExample::of($context->resource)],
        );

        $operation->addResponse(Schemas::response(200, 'The operations that the mutation wrote', (new ResultSchema($context->components))->reference($context->resource)));
    }
}
