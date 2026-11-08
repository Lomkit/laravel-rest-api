<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Contracts\BatchableAction;
use Lomkit\Rest\Scramble\Actions\ActionResponseSchema;
use Lomkit\Rest\Scramble\DocumentationContext;
use Lomkit\Rest\Scramble\Schemas;

final readonly class BatchDocumenter implements EndpointDocumenter
{
    #[\Override]
    public function document(Operation $operation, DocumentationContext $context): void
    {
        $operation->summary("Batch progress of {$context->name} actions");

        $actions = array_filter($context->resource->definition()->actions(), fn (Action $action): bool => $action instanceof BatchableAction);
        $schema = new Schema;
        $schema->type = (new StringType)->enum(array_values(array_map(fn (Action $action): string => $action->uriKey(), $actions)));

        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter && $parameter->in === 'path' && $parameter->name === 'action' && $actions !== []) {
                $parameter->setSchema($schema);
            }
        }

        $operation->addResponse(Schemas::response(200, 'The progress of the batch', (new ActionResponseSchema($context->components))->progress()));
    }
}
