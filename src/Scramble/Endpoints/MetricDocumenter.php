<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Dedoc\Scramble\Support\Generator\Operation;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Scramble\DocumentationContext;
use Lomkit\Rest\Scramble\Metrics\MetricSchema;
use Lomkit\Rest\Scramble\Schemas;
use stdClass;

final readonly class MetricDocumenter implements EndpointDocumenter
{
    #[\Override]
    public function document(Operation $operation, DocumentationContext $context): void
    {
        $metric = $context->declaration();

        if (! $metric instanceof Metric) {
            return;
        }

        $schema = new MetricSchema($context->components);

        $operation->summary($metric->name());
        Schemas::requestBody($operation, $schema->body($context->resource, $metric), ['default' => new stdClass]);
        $operation->addResponse(Schemas::response(200, 'The result of the metric', $schema->response($metric)));
    }
}
