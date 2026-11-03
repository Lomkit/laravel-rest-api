<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Authorization\Ability;

final readonly class GateVocabulary
{
    /** @param array<string, GateAbility> $gates */
    private function __construct(private array $gates) {}

    public static function of(): self
    {
        $gates = [];

        foreach (Ability::cases() as $ability) {
            $gates[$ability->value] = new GateAbility($ability);
        }

        return new self($gates);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->gates);
    }

    public function find(mixed $name): ?GateAbility
    {
        return is_string($name) ? $this->gates[$name] ?? null : null;
    }
}
