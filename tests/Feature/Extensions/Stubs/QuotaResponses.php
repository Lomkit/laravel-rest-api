<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Http\Routing\Endpoint;

final class QuotaResponses extends DocumentedResponses
{
    #[\Override]
    public static function name(): string
    {
        return 'quota';
    }

    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void
    {
        if (in_array($context->endpoint, [Endpoint::Search, Endpoint::Mutate], true)) {
            $context->response(429, 'Exceeded', 'The monthly quota is reached', self::message());
        }
    }
}
