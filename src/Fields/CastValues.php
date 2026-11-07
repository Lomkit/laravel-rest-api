<?php

namespace Lomkit\Rest\Fields;

use Lomkit\Rest\Exceptions\InvalidFieldException;

final readonly class CastValues
{
    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $names
     */
    private function __construct(
        private array $values,
        private array $names,
        private string $owner,
    ) {}

    public static function of(InputValues $inputs, string $owner): self
    {
        return new self($inputs->toArray(), $inputs->names(), $owner);
    }

    public function get(string $name): mixed
    {
        return $this->has($name) ? $this->values[$name] : null;
    }

    public function has(string $name): bool
    {
        if (! in_array($name, $this->names, true)) {
            throw InvalidFieldException::undeclaredInput($this->owner, $name);
        }

        return array_key_exists($name, $this->values);
    }

    public function filled(string $name): bool
    {
        return $this->get($name) !== null;
    }

    /** @return array<string, mixed> */
    public function only(string ...$names): array
    {
        return array_intersect_key($this->values, array_flip(array_filter($names, $this->has(...))));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values;
    }
}
