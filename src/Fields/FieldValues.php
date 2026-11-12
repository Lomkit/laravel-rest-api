<?php

namespace Lomkit\Rest\Fields;

use Carbon\CarbonImmutable;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use UnitEnum;

abstract readonly class FieldValues
{
    protected function __construct(protected CastValues $values) {}

    public function __get(string $name): mixed
    {
        return $this->values->get($name);
    }

    public function __isset(string $name): bool
    {
        return $this->values->filled($name);
    }

    public function has(string $name): bool
    {
        return $this->values->has($name);
    }

    public function filled(string $name): bool
    {
        return $this->values->filled($name);
    }

    public function string(string $name): ?string
    {
        $value = $this->values->get($name);

        if ($value === null || is_string($value)) {
            return $value;
        }

        throw $this->unexpected($name, 'string', $value);
    }

    public function integer(string $name): ?int
    {
        $value = $this->values->get($name);

        if ($value === null || is_int($value)) {
            return $value;
        }

        if (! is_string($value)) {
            throw $this->unexpected($name, 'int', $value);
        }

        return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? throw $this->unexpected($name, 'int', $value);
    }

    public function float(string $name): ?float
    {
        $value = $this->values->get($name);

        if ($value === null || is_float($value)) {
            return $value;
        }

        if (! is_numeric($value)) {
            throw $this->unexpected($name, 'float', $value);
        }

        return (float) $value;
    }

    public function boolean(string $name): ?bool
    {
        $value = $this->values->get($name);

        if ($value === null || is_bool($value)) {
            return $value;
        }

        throw $this->unexpected($name, 'bool', $value);
    }

    public function date(string $name): ?CarbonImmutable
    {
        $value = $this->values->get($name);

        if ($value === null || $value instanceof CarbonImmutable) {
            return $value;
        }

        throw $this->unexpected($name, CarbonImmutable::class, $value);
    }

    /**
     * @template E of UnitEnum
     *
     * @param  class-string<E>  $enum
     * @return E|null
     */
    public function enum(string $name, string $enum): ?UnitEnum
    {
        $value = $this->values->get($name);

        if ($value === null || $value instanceof $enum) {
            return $value;
        }

        throw $this->unexpected($name, $enum, $value);
    }

    /** @return array<array-key, mixed>|null */
    public function array(string $name): ?array
    {
        $value = $this->values->get($name);

        if ($value === null || is_array($value)) {
            return $value;
        }

        throw $this->unexpected($name, 'array', $value);
    }

    /** @return array<string, mixed> */
    public function only(string ...$names): array
    {
        return $this->values->only(...$names);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values->toArray();
    }

    private function unexpected(string $name, string $expected, mixed $value): InvalidFieldException
    {
        return InvalidFieldException::unexpectedInputType($this->values->owner(), $name, $expected, $value);
    }
}
