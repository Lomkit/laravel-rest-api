<?php

namespace Lomkit\Rest\Validation;

use Lomkit\Rest\Authorization\Check;

final class ParsedRules
{
    /** @var array<string, list<mixed>> */
    private array $rules = [];

    /** @var array<string, list<mixed>>|null */
    private ?array $built = null;

    /** @var array<string, Check> */
    private array $checks = [];

    /** @var array<string, mixed> */
    private array $projections = [];

    /** @var array<string, string> */
    private array $labels = [];

    private bool $partial = false;

    private readonly Violations $violations;

    public function __construct()
    {
        $this->violations = new Violations;
    }

    /** @param list<mixed> $rules */
    public function add(string $key, array $rules): void
    {
        $this->rules[$key] = [...$this->rules[$key] ?? [], ...$rules];
    }

    /** @param list<mixed> $rules */
    public function addWhenPresent(Cursor $value, array $rules): void
    {
        if ($value->present) {
            $this->add($value->path, $rules);
        }
    }

    public function holds(string $key, mixed $rule): bool
    {
        return in_array($rule, $this->rules[$key] ?? [], true);
    }

    public function reject(Violation $violation): void
    {
        $this->add($violation->key, []);
        $this->violations->add($violation);
    }

    public function violations(): Violations
    {
        return $this->violations;
    }

    public function reach(Check $check): void
    {
        $this->checks[$check->path."\0".$check->key()] ??= $check;
    }

    public function project(string $key, mixed $value): void
    {
        $this->projections[$key] = $value;
    }

    public function label(string $key, string $label): void
    {
        $this->labels[KeyPath::unescape($key)] = $label;
    }

    public function partial(): void
    {
        $this->partial = true;
    }

    public function isPartial(): bool
    {
        return $this->partial;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return $this->built ??= $this->violations->applyTo($this->rules);
    }

    /**
     * @param  array<array-key, mixed>  $rules
     * @return array<array-key, mixed>
     */
    public function merge(array $rules): array
    {
        $parsed = $this->rules();
        $merged = [];

        foreach ($rules as $key => $entry) {
            if (! array_key_exists($key, $parsed)) {
                $merged[$key] = $entry;

                continue;
            }

            $list = RuleList::of($entry);
            $added = array_filter([...$list->plain, ...$list->nodes], fn (mixed $rule): bool => ! in_array($rule, $parsed[$key], true));
            $merged[$key] = [...$parsed[$key], ...array_values($added)];
        }

        return $merged + $parsed;
    }

    /** @return list<Check> */
    public function checks(): array
    {
        return array_values($this->checks);
    }

    /** @return array<string, mixed> */
    public function projections(): array
    {
        return $this->projections;
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    public function data(array $input): array
    {
        foreach ($this->projections as $key => $value) {
            KeyPath::set($input, $key, $value);
        }

        return $input;
    }

    /** @return array<string, string> */
    public function labels(): array
    {
        return $this->labels;
    }
}
