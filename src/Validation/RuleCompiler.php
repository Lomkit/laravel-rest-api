<?php

namespace Lomkit\Rest\Validation;

use Illuminate\Support\Str;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Rules\ArrayWithKey;

final class RuleCompiler
{
    /** @var array<string, list<mixed>> */
    private array $rules = [];

    /** @var array<string, string> */
    private array $labels = [];

    /** @var array<string, mixed> */
    private array $projections = [];

    /** @var array<string, list<mixed>> */
    private array $wildcards = [];

    /** @var array<string, Check> */
    private array $reach = [];

    /** @param array<array-key, mixed> $data */
    private function __construct(private readonly array $data) {}

    /**
     * @param  array<array-key, mixed>  $rules
     * @param  array<array-key, mixed>  $data
     */
    public static function compile(array $rules, array $data): CompiledRules
    {
        $compiler = new self($data);
        $compiler->add($rules);

        return new CompiledRules($compiler->resolved() + $compiler->wildcards, $compiler->labels, $compiler->projections, array_values($compiler->reach));
    }

    /** @param array<array-key, mixed> $rules */
    public function add(array $rules): void
    {
        $this->addAll($rules, false);
    }

    /** @param array<array-key, mixed> $rules */
    public function addExpanded(array $rules): void
    {
        $this->addAll($rules, true);
    }

    public function project(string $key, mixed $value): void
    {
        $this->projections[$key] = $value;
    }

    public function reach(Check $check): void
    {
        $this->reach[$check->path."\0".$check->key()] ??= $check;
    }

    public function label(string $key, string $label): void
    {
        $this->labels[KeyPath::unescape($key)] = $label;
    }

    /** @param array<array-key, mixed> $rules */
    private function addAll(array $rules, bool $expanded): void
    {
        $wildcards = [];

        foreach ($rules as $key => $list) {
            if (str_contains((string) $key, '*')) {
                $wildcards[(string) $key] = $list;

                continue;
            }

            $this->addKey((string) $key, RuleList::of($list), $expanded);
        }

        foreach ($wildcards as $key => $list) {
            $this->expand($key, RuleList::of($list));
        }
    }

    private function expand(string $key, RuleList $list): void
    {
        $keys = WildcardKeys::of($this->data, $key);
        $expandable = $list->expandable();
        $kept = $list->keptOnWildcard();

        $this->rejectUnnamed($keys->unnamed());

        foreach ($keys->matches() as [$explicit, $indices]) {
            if (! $expandable->isEmpty()) {
                $this->addKey($explicit, $expandable->withWildcards($indices), true);
            } elseif ($kept !== []) {
                $this->rules[$explicit] ??= [];
            }
        }

        if ($kept !== []) {
            $this->wildcards[$key] = [...($this->wildcards[$key] ?? []), ...$kept];
        }
    }

    private function addKey(string $key, RuleList $list, bool $expanded): void
    {
        if ($expanded) {
            $this->labelAsWritten($key);
        }

        $this->append($key, $list->plain);

        if ($list->nodes === [] || ! $this->has($key)) {
            return;
        }

        $value = $this->value($key);

        if ($list->skipsNodesFor($value)) {
            return;
        }

        if (! is_array($value)) {
            $this->append($key, in_array('array', $this->rules[$key] ?? [], true) ? [] : ['array']);

            return;
        }

        foreach ($list->nodes as $node) {
            $node->compile($this, $key, $value);
        }
    }

    /** @param list<mixed> $rules */
    private function append(string $key, array $rules): void
    {
        if ($rules !== []) {
            $this->rules[$key] = [...($this->rules[$key] ?? []), ...$rules];
        }
    }

    /** @param array<string, list<int|string>> $unnamed */
    private function rejectUnnamed(array $unnamed): void
    {
        foreach ($unnamed as $container => $keys) {
            $value = $this->value($container);

            if ($container === '' || ! is_array($value)) {
                continue;
            }

            $named = array_diff(array_map(strval(...), array_keys($value)), array_map(strval(...), $keys));
            $this->append($container, [new ArrayWithKey(array_values($named))]);
        }
    }

    private function labelAsWritten(string $key): void
    {
        $written = KeyPath::unescape($key);

        if (! isset($this->labels[$written]) && str_replace('_', ' ', Str::snake($written)) !== $written) {
            $this->labels[$written] = $written;
        }
    }

    /** @return array<string, list<mixed>> */
    private function resolved(): array
    {
        $resolved = [];

        foreach ($this->rules as $key => $rules) {
            if (! in_array('sometimes', $rules, true)) {
                $resolved[$key] = $rules;
            } elseif ($this->has($key)) {
                $resolved[$key] = array_values(array_filter($rules, fn (mixed $rule): bool => $rule !== 'sometimes'));
            }
        }

        return $resolved;
    }

    private function has(string $key): bool
    {
        return array_key_exists($key, $this->projections) || KeyPath::has($this->data, $key);
    }

    private function value(string $key): mixed
    {
        return array_key_exists($key, $this->projections) ? $this->projections[$key] : KeyPath::get($this->data, $key);
    }
}
