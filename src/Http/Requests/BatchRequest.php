<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Http\Routing\Endpoint;

class BatchRequest extends RestRequest
{
    #[\Override]
    public function endpoint(): Endpoint
    {
        return Endpoint::Batch;
    }
}
