<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Http\Routing\Endpoint;

class ForbiddenResponses extends DocumentedResponses
{
    #[\Override]
    public static function name(): string
    {
        return 'forbidden';
    }

    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void
    {
        if ($context->endpoint === Endpoint::Search) {
            $context->response(403, 'Refused', 'The plan refuses the search', self::message());
        }
    }
}
