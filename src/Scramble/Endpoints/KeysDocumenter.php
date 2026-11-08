<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Dedoc\Scramble\Support\Generator\Operation;
use Lomkit\Rest\Scramble\Components\KeySchema;
use Lomkit\Rest\Scramble\Components\PageSchema;
use Lomkit\Rest\Scramble\ConfirmationSchema;
use Lomkit\Rest\Scramble\DocumentationContext;
use Lomkit\Rest\Scramble\Schemas;

final readonly class KeysDocumenter implements EndpointDocumenter
{
    public function __construct(
        private string $verb,
        private string $result,
    ) {}

    #[\Override]
    public function document(Operation $operation, DocumentationContext $context): void
    {
        $operation->summary("{$this->verb} {$context->name}");

        Schemas::requestBody(
            $operation,
            Schemas::required(['resources' => KeySchema::list($context->resource), ...ConfirmationSchema::token($context->resource, $context->endpoint)], ['confirmation']),
            ['default' => ['resources' => [KeySchema::example($context->resource)]]],
        );

        $operation->addResponse(Schemas::response(200, $this->result, (new PageSchema($context->components))->rows($context->resource)));
    }
}
