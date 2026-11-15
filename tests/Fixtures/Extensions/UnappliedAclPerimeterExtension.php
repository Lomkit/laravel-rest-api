<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Tests\Fixtures\Atlas\JournalResource;

final class UnappliedAclPerimeterExtension extends AclPerimeterExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'unapplied';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource !== JournalResource::class;
    }
}
