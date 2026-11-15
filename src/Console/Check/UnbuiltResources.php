<?php

namespace Lomkit\Rest\Console\Check;

use Throwable;

/** @internal */
final class UnbuiltResources
{
    /** @var array<string, array{error: string, through: list<string>}> */
    private array $failures = [];

    public function add(string $resource, Throwable $exception, ?string $through = null): void
    {
        $failure = $this->failures[$resource] ?? ['error' => $exception->getMessage(), 'through' => []];

        if ($through !== null && ! in_array($through, $failure['through'], true)) {
            $failure['through'][] = $through;
        }

        $this->failures[$resource] = $failure;
    }

    /** @return list<string> */
    public function problems(): array
    {
        $problems = [];

        foreach ($this->failures as $resource => $failure) {
            $through = $failure['through'] === [] ? '' : ' (reached through '.implode(', ', $failure['through']).')';
            $problems[] = "{$resource} cannot be built{$through}, so no other check reads it: {$failure['error']}";
        }

        return $problems;
    }
}
