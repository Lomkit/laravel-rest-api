<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Http\Routing\Endpoint;

final class RivalQuotaResponses extends DocumentedResponses
{
    #[\Override]
    public static function name(): string
    {
        return 'limits';
    }

    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void
    {
        if ($context->endpoint === Endpoint::Search) {
            $context->response(429, 'Exceeded', 'The daily quota is reached', self::message());
        }
    }
}
