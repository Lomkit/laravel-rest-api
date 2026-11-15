<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;
use RuntimeException;

final class ThrowingNameExtension extends Extension implements NotesResources
{
    public const string MESSAGE = 'The name store is not reachable.';

    #[\Override]
    public static function name(): string
    {
        throw new RuntimeException(self::MESSAGE);
    }
}
