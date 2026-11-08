<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Dedoc\Scramble\Support\Generator\Operation;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Contracts\BatchableAction;
use Lomkit\Rest\Scramble\Actions\ActionExample;
use Lomkit\Rest\Scramble\Actions\ActionResponseSchema;
use Lomkit\Rest\Scramble\Actions\ActionSchema;
use Lomkit\Rest\Scramble\DocumentationContext;
use Lomkit\Rest\Scramble\Schemas;

final readonly class OperateDocumenter implements EndpointDocumenter
{
    #[\Override]
    public function document(Operation $operation, DocumentationContext $context): void
    {
        $action = $context->declaration();

        if (! $action instanceof Action) {
            return;
        }

        $operation->summary($action->name());

        Schemas::requestBody(
            $operation,
            (new ActionSchema($context->components))->body($context->resource, $action),
            ['default' => ActionExample::of($context->resource, $action)],
        );

        $operation->addResponse(Schemas::response(200, 'The number of rows that the action ran on and its result', (new ActionResponseSchema($context->components))->operate($action instanceof BatchableAction)));
    }
}
