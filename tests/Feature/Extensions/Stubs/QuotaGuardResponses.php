<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Kinds\GuardsWrites;
use Lomkit\Rest\Http\Routing\Endpoint;

final class QuotaGuardResponses extends DocumentedResponses implements GuardsWrites
{
    #[\Override]
    public function guardWrite(WriteGuardContext $context): void
    {
        $context->refuse('The monthly quota is reached.', 429);
    }

    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void
    {
        if ($context->endpoint === Endpoint::Mutate) {
            $context->response(429, 'Exceeded', 'The monthly quota is reached', self::message());
        }
    }
}
