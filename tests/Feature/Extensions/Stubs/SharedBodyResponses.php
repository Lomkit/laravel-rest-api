<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Http\Routing\Endpoint;

final class SharedBodyResponses extends DocumentedResponses
{
    public static ?ObjectType $body = null;

    public static bool $registersAgain = true;

    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void
    {
        $body = self::$body ??= self::message();

        if ($context->endpoint === Endpoint::Search) {
            $context->response(429, 'Refused', 'The request is refused', $body);
        }

        if ($context->endpoint === Endpoint::Mutate) {
            $body->addProperty('remaining', new StringType);

            if (self::$registersAgain) {
                $context->response(429, 'Refused', 'The request is refused', $body);
            }
        }
    }

    public static function reset(bool $registersAgain): void
    {
        self::$body = null;
        self::$registersAgain = $registersAgain;
    }
}
