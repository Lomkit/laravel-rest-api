<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Lomkit\Rest\Http\Routing\Endpoint;

final class EndpointDocumenters
{
    public static function for(Endpoint $endpoint): ?EndpointDocumenter
    {
        return match ($endpoint) {
            Endpoint::Details => new DetailsDocumenter,
            Endpoint::Search => new SearchDocumenter,
            Endpoint::Mutate => new MutateDocumenter,
            Endpoint::Destroy => new KeysDocumenter('Delete'),
            Endpoint::Restore => new KeysDocumenter('Restore'),
            Endpoint::Operate, Endpoint::Metric, Endpoint::Batch, Endpoint::ForceDestroy => null,
        };
    }
}
