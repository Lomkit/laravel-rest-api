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

    /** @return ($required is true ? string : string|null) */
    public function string(string $name, bool $required = false): ?string
    {
        $value = $this->read($name, $required);

        if ($value === null || is_string($value)) {
            return $value;
        }

        throw $this->unexpected($name, 'string', $value);
    }

    /** @return ($required is true ? int : int|null) */
    public function integer(string $name, bool $required = false): ?int
    {
        $value = $this->read($name, $required);

        if ($value === null || is_int($value)) {
            return $value;
        }

        if (! is_string($value)) {
            throw $this->unexpected($name, 'int', $value);
        }

        return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? throw $this->unexpected($name, 'int', $value);
    }

    /** @return ($required is true ? float : float|null) */
    public function float(string $name, bool $required = false): ?float
    {
        $value = $this->read($name, $required);

        if ($value === null || is_float($value)) {
            return $value;
        }

        if (! is_numeric($value)) {
            throw $this->unexpected($name, 'float', $value);
        }

        return (float) $value;
    }

    /** @return ($required is true ? bool : bool|null) */
    public function boolean(string $name, bool $required = false): ?bool
    {
        $value = $this->read($name, $required);

        if ($value === null || is_bool($value)) {
            return $value;
        }

        throw $this->unexpected($name, 'bool', $value);
    }

    /** @return ($required is true ? CarbonImmutable : CarbonImmutable|null) */
    public function date(string $name, bool $required = false): ?CarbonImmutable
    {
        $value = $this->read($name, $required);

        if ($value === null || $value instanceof CarbonImmutable) {
            return $value;
        }

        throw $this->unexpected($name, CarbonImmutable::class, $value);
    }

    /**
     * @template E of UnitEnum
     *
     * @param  class-string<E>  $enum
     * @return ($required is true ? E : E|null)
     */
    public function enum(string $name, string $enum, bool $required = false): ?UnitEnum
    {
        $value = $this->read($name, $required);

        if ($value === null || $value instanceof $enum) {
            return $value;
        }

        throw $this->unexpected($name, $enum, $value);
    }

    /** @return ($required is true ? array<array-key, mixed> : array<array-key, mixed>|null) */
    public function array(string $name, bool $required = false): ?array
    {
        $value = $this->read($name, $required);

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

    private function read(string $name, bool $required): mixed
    {
        $value = $this->values->get($name);

        if ($value === null && $required) {
            throw InvalidFieldException::requiredInput($this->values->owner(), $name);
        }

        return $value;
    }

    private function unexpected(string $name, string $expected, mixed $value): InvalidFieldException
    {
        return InvalidFieldException::unexpectedInputType($this->values->owner(), $name, $expected, $value);
    }
}
