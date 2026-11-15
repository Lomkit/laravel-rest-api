<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Http\Routing\Endpoint;

final class CreatedResponses extends DocumentedResponses
{
    #[\Override]
    public static function name(): string
    {
        return 'created';
    }

    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void
    {
        if ($context->endpoint === Endpoint::Search) {
            $context->response(201, 'Created', 'Created', self::message());
        }
    }
}
