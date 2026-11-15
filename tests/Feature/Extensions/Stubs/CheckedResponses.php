<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Http\Routing\Endpoint;

final class CheckedResponses extends DocumentedResponses
{
    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void
    {
        MetaLog::$documented[] = [$context->documents(429), $context->documents(403)];

        if ($context->endpoint === Endpoint::Search) {
            $context->response(429, 'Exceeded', 'The monthly quota is reached', self::message());
            MetaLog::$documented[] = [$context->documents(429), $context->documents(403)];
        }
    }
}
