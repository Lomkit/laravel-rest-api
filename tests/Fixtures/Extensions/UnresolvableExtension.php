<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;

final class UnresolvableExtension extends Extension implements NotesResources
{
    #[\Override]
    public static function name(): string
    {
        return 'unresolvable';
    }

    public function __construct(public readonly string $token) {}
}
