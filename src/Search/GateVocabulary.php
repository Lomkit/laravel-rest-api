<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Authorization\Ability;
use Lomkit\Rest\Exceptions\InvalidResourceException;

final readonly class GateVocabulary
{
    private const string NAME = '/^[a-z][a-zA-Z0-9]*$/';

    /** @param array<string, GateAbility> $gates */
    private function __construct(private array $gates) {}

    /**
     * @param  array<mixed>  $declared
     * @param  list<DeclaredGateList>  $extensions
     */
    public static function of(array $declared, string $owner, bool $softDeletes, array $extensions = []): self
    {
        $gates = [];

        foreach (Ability::cases() as $ability) {
            if ($softDeletes || ! $ability->needsSoftDeletes()) {
                $gates[$ability->value] = new GateAbility($ability);
            }
        }

        $resource = new DeclaredGateList($declared, static fn (int $index, mixed $name): InvalidResourceException => InvalidResourceException::invalidGate($owner, $index, $name));

        foreach ([$resource, ...$extensions] as $list) {
            $listed = [];

            foreach (array_values($list->names) as $index => $name) {
                if (! is_string($name) || ! self::declarable($name) || isset($listed[$name])) {
                    throw $list->refuse($index, $name);
                }

                $listed[$name] = true;
                $gates[$name] ??= new GateAbility($name);
            }
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

    private static function declarable(string $name): bool
    {
        return preg_match(self::NAME, $name) === 1 && $name !== 'viewAny' && Ability::tryFrom($name) === null;
    }
}
