<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;
use RuntimeException;

final class FailingExtension extends Extension implements NotesResources
{
    public const string MESSAGE = 'The notes store is not reachable.';

    #[\Override]
    public static function name(): string
    {
        return 'failing';
    }

    public function __construct()
    {
        throw new RuntimeException(self::MESSAGE);
    }
}
