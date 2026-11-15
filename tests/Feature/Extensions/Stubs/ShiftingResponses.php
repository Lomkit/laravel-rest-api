<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Http\Routing\Endpoint;

final class ShiftingResponses extends DocumentedResponses
{
    #[\Override]
    public static function name(): string
    {
        return 'shifting';
    }

    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void
    {
        if ($context->endpoint === Endpoint::Search) {
            $context->response(429, 'Exceeded', 'The search quota is reached', self::message());
        }

        if ($context->endpoint === Endpoint::Mutate) {
            $context->response(429, 'Exceeded', 'The write quota is reached', self::message());
        }
    }
}
