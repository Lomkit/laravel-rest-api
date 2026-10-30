<?php

namespace Lomkit\Rest\Validation;

use Illuminate\Contracts\Validation\CompilableRules;
use Lomkit\Rest\Rules\RestRule;

final readonly class RuleList
{
    /**
     * @param  list<mixed>  $plain
     * @param  list<RestRule>  $nodes
     */
    private function __construct(public array $plain, public array $nodes) {}

    public static function of(mixed $rules): self
    {
        $plain = [];
        $nodes = [];

        foreach (self::listOf($rules) as $rule) {
            if ($rule instanceof RestRule) {
                $nodes[] = $rule;

                continue;
            }

            $plain[] = $rule;
        }

        return new self($plain, $nodes);
    }

    public function isEmpty(): bool
    {
        return $this->plain === [] && $this->nodes === [];
    }

    public function holds(string $rule): bool
    {
        return in_array($rule, $this->plain, true);
    }

    public function without(string $rule): self
    {
        return new self(array_values(array_filter($this->plain, fn (mixed $plain): bool => $plain !== $rule)), $this->nodes);
    }

    /** @return list<mixed> */
    public function keptOnWildcard(): array
    {
        $kept = array_slice($this->plain, $this->keptFrom());

        return $kept === [] ? [] : $this->withSometimes($kept);
    }

    public function expandable(): self
    {
        $expandable = array_slice($this->plain, 0, $this->keptFrom());

        return new self(count($expandable) === count($this->plain) ? $expandable : $this->withSometimes($expandable), $this->nodes);
    }

    /** @param list<string> $indices */
    public function withWildcards(array $indices): self
    {
        return new self(RuleReferences::substituteWildcards($this->plain, $indices), $this->nodes);
    }

    public function skipsNodesFor(mixed $value): bool
    {
        if (is_string($value) && trim($value) === '') {
            return true;
        }

        return $value === null && $this->holds('nullable');
    }

    private function keptFrom(): int
    {
        foreach ($this->plain as $position => $rule) {
            if (self::staysOnWildcard($rule)) {
                return $position;
            }
        }

        return count($this->plain);
    }

    /**
     * @param  list<mixed>  $rules
     * @return list<mixed>
     */
    private function withSometimes(array $rules): array
    {
        if (! $this->holds('sometimes') || in_array('sometimes', $rules, true)) {
            return $rules;
        }

        return ['sometimes', ...$rules];
    }

    /** @return list<mixed> */
    private static function listOf(mixed $rules): array
    {
        return match (true) {
            is_string($rules) => explode('|', $rules),
            is_array($rules) => array_values($rules),
            default => [$rules],
        };
    }

    private static function staysOnWildcard(mixed $rule): bool
    {
        if ($rule instanceof CompilableRules) {
            return true;
        }

        return is_string($rule) && ($rule === 'distinct' || str_starts_with($rule, 'distinct:'));
    }
}
