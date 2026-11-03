<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Authorization\Ability;
use Lomkit\Rest\Exceptions\InvalidResourceException;

final readonly class GateVocabulary
{
    private const string NAME = '/^[a-z][a-zA-Z0-9]*$/';

    /** @param array<string, GateAbility> $gates */
    private function __construct(private array $gates) {}

    /** @param array<mixed> $declared */
    public static function of(array $declared, string $owner): self
    {
        $gates = [];

        foreach (Ability::cases() as $ability) {
            $gates[$ability->value] = new GateAbility($ability);
        }

        foreach (array_values($declared) as $index => $name) {
            if (! is_string($name) || preg_match(self::NAME, $name) !== 1 || $name === 'viewAny' || isset($gates[$name])) {
                throw InvalidResourceException::invalidGate($owner, $index, $name);
            }

            $gates[$name] = new GateAbility($name);
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
