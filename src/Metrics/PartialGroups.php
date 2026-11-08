<?php

namespace Lomkit\Rest\Metrics;

final readonly class PartialGroups implements GroupValues
{
    /** @param list<array{key: int|string|bool|null, partials: list<int|float|null>}> $groups */
    public function __construct(
        private Aggregate $function,
        private bool $integer,
        private array $groups,
    ) {}

    #[\Override]
    public function groups(): array
    {
        return array_map(fn (array $group): array => ['key' => $group['key'], 'value' => $this->function->value($group['partials'])], $this->groups);
    }

    #[\Override]
    public function others(array $kept): int|float|null
    {
        $folded = array_filter($this->groups, fn (array $group): bool => ! in_array($group['key'], $kept, true));

        if ($folded === []) {
            return null;
        }

        $partials = $this->function->partials([null, null], $this->integer);

        foreach ($folded as $group) {
            $partials = $this->function->merge($partials, $group['partials']);
        }

        return $this->function->value($partials);
    }
}
