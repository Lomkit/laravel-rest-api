<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Closure;

/** @internal */
final class IndexLimits
{
    /** @var array<string, ?int> */
    private array $read = [];

    /** @param Closure(): ?int $read */
    public function remember(string $index, Closure $read): ?int
    {
        if (! array_key_exists($index, $this->read)) {
            $this->read[$index] = $read();
        }

        return $this->read[$index];
    }
}
