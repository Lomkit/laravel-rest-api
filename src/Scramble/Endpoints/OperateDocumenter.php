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
        self::warnings($operation, $action);

        Schemas::requestBody(
            $operation,
            (new ActionSchema($context->components))->body($context->resource, $action),
            ['default' => ActionExample::of($context->resource, $action)],
        );

        $operation->addResponse(Schemas::response(200, 'The number of rows that the action ran on and its result', (new ActionResponseSchema($context->components))->operate($action instanceof BatchableAction)));
    }

    private static function warnings(Operation $operation, Action $action): void
    {
        $lines = [];

        if ($action->destructive) {
            $operation->setExtensionProperty('lomkit-destructive', true);
            $lines[] = 'The action is destructive: a hint for clients, the endpoint runs it the same way.';
        }

        if ($action->confirm !== null) {
            $operation->setExtensionProperty('lomkit-confirm', $action->confirm);
            $lines[] = "The text that a client shows before it runs the action: {$action->confirm}";
        }

        if ($action->requiresConfirmation()) {
            $lines[] = 'The action can answer 428: send the same body again with the token of the answer in `confirmation`.';
        }

        if ($lines !== []) {
            $operation->description(implode("\n\n", $lines));
        }
    }
}
