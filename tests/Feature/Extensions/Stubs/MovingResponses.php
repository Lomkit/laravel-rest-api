<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Http\Routing\Endpoint;

final class MovingResponses extends DocumentedResponses
{
    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void
    {
        if ($context->endpoint === Endpoint::Search) {
            $context->response(429, 'Refused', 'The request is refused', self::message());
        }

        if ($context->endpoint === Endpoint::Mutate) {
            $context->response(423, 'Refused', 'The request is refused', self::message());
        }
    }
}
