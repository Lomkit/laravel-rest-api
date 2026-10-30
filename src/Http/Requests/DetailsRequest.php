<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Http\Routing\Endpoint;

class DetailsRequest extends RestRequest
{
    #[\Override]
    public function endpoint(): Endpoint
    {
        return Endpoint::Details;
    }
}
