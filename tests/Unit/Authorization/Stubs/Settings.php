<?php

namespace Lomkit\Rest\Tests\Unit\Authorization\Stubs;

final class Settings
{
    /** @param array<string, mixed> $values */
    public function __construct(public array $values) {}
}
