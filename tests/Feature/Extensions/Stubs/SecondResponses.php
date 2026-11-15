<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Http\Routing\Endpoint;

final class SecondResponses extends DocumentedResponses
{
    public static string $named = 'second';

    /** @var list<array{list<Endpoint>, int, string, string}> */
    public static array $responses = [];

    public static function reset(): void
    {
        self::$named = 'second';
        self::$responses = [];
    }

    #[\Override]
    public static function name(): string
    {
        return self::$named;
    }

    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void
    {
        self::document($context, self::$responses);
    }
}
