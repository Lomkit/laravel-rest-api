<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Lomkit\Rest\Http\Routing\Endpoint;

final class EndpointDocumenters
{
    public static function for(Endpoint $endpoint): EndpointDocumenter
    {
        return match ($endpoint) {
            Endpoint::Details => new DetailsDocumenter,
            Endpoint::Search => new SearchDocumenter,
            Endpoint::Mutate => new MutateDocumenter,
            Endpoint::Operate => new OperateDocumenter,
            Endpoint::Metric => new MetricDocumenter,
            Endpoint::Batch => new BatchDocumenter,
            Endpoint::Destroy => new KeysDocumenter('Delete'),
            Endpoint::Restore => new KeysDocumenter('Restore'),
            Endpoint::ForceDestroy => new KeysDocumenter('Force delete'),
        };
    }
}
