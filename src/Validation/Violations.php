<?php

namespace Lomkit\Rest\Validation;

final class Violations
{
    /** @var list<Violation> */
    private array $violations = [];

    public function add(Violation $violation): void
    {
        $this->violations[] = $violation;
    }

    public function isEmpty(): bool
    {
        return $this->violations === [];
    }

    public function has(string $key): bool
    {
        foreach ($this->violations as $violation) {
            if ($violation->key === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, list<mixed>>
     */
    public function applyTo(array $rules): array
    {
        $rejected = [];

        foreach ($this->violations as $violation) {
            $rejected[$violation->key][] = new Rejected($violation);
        }

        foreach ($rejected as $key => $list) {
            $rules[(string) $key] = [...$list, ...$rules[$key] ?? []];
        }

        return $rules;
    }
}
