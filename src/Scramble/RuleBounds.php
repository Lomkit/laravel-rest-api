<?php

namespace Lomkit\Rest\Scramble;

final readonly class RuleBounds
{
    /** @param array<string, list<string>> $rules */
    public function __construct(private array $rules) {}

    public function number(int|float $value): int|float
    {
        $lower = $this->lower();
        $upper = $this->upper();

        if ($lower !== null && $value < $lower) {
            $value = is_int($value) ? (int) ceil($lower) : $lower;
        }

        if ($upper !== null && $value > $upper) {
            $value = is_int($value) ? (int) floor($upper) : $upper;
        }

        return $value;
    }

    public function text(string $value): string
    {
        $length = max(mb_strlen($value), (int) ceil($this->lower() ?? 0));
        $upper = $this->upper();

        if ($upper !== null) {
            $length = min($length, max(0, (int) floor($upper)));
        }

        return mb_substr(str_pad($value, $length, 'a'), 0, $length);
    }

    private function lower(): ?float
    {
        $greater = $this->bound('Gt', 0);

        return self::extreme([
            $this->bound('Min', 0),
            $this->bound('Gte', 0),
            $this->bound('Size', 0),
            $this->bound('Between', 0),
            $greater === null ? null : $greater + 1,
        ], true);
    }

    private function upper(): ?float
    {
        $less = $this->bound('Lt', 0);

        return self::extreme([
            $this->bound('Max', 0),
            $this->bound('Lte', 0),
            $this->bound('Size', 0),
            $this->bound('Between', 1),
            $less === null ? null : $less - 1,
        ], false);
    }

    /** @param list<float|null> $bounds */
    private static function extreme(array $bounds, bool $highest): ?float
    {
        $given = array_values(array_filter($bounds, static fn (?float $bound): bool => $bound !== null));

        if ($given === []) {
            return null;
        }

        return $highest ? max($given) : min($given);
    }

    private function bound(string $rule, int $position): ?float
    {
        $parameter = $this->rules[$rule][$position] ?? null;

        return is_numeric($parameter) ? (float) $parameter : null;
    }
}
