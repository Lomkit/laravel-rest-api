<?php

namespace Lomkit\Rest\Tests\Fixtures;

final class ReceivedFields
{
    /** @var list<array{string, array<string, mixed>}> */
    private array $calls = [];

    /** @param array<string, mixed> $fields */
    public function record(string $hook, array $fields): void
    {
        $this->calls[] = [$hook, $fields];
    }

    /** @return list<array{string, array<string, mixed>}> */
    public function calls(): array
    {
        return $this->calls;
    }
}
