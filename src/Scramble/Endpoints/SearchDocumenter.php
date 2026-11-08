<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Dedoc\Scramble\Support\Generator\Operation;
use Lomkit\Rest\Scramble\Components\PageSchema;
use Lomkit\Rest\Scramble\DocumentationContext;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Search\SearchExample;
use Lomkit\Rest\Scramble\Search\SearchSchema;

final readonly class SearchDocumenter implements EndpointDocumenter
{
    #[\Override]
    public function document(Operation $operation, DocumentationContext $context): void
    {
        $operation->summary("Search {$context->name}");

        $resource = $context->resource;
        $example = SearchExample::of($resource);

        Schemas::requestBody(
            $operation,
            Schemas::object(['search' => (new SearchSchema($context->components))->reference($resource)]),
            $example === null ? [] : ['default' => $example],
        );

        $operation->addResponse(Schemas::response(200, 'A page of rows', (new PageSchema($context->components))->paginated($context->resource)));
    }
}
