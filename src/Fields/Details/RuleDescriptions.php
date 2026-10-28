<?php

namespace Lomkit\Rest\Fields\Details;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Stringable;

final readonly class RuleDescriptions
{
    /** @param string|array<mixed> $rules */
    public function __construct(private string|array $rules) {}

    /** @return list<string> */
    public function toArray(): array
    {
        $rules = is_string($this->rules) ? explode('|', $this->rules) : $this->rules;
        $descriptions = [];

        foreach ($rules as $rule) {
            $description = $this->describe($rule);

            if ($description !== null && $description !== '') {
                $descriptions[] = $description;
            }
        }

        return $descriptions;
    }

    private function describe(mixed $rule): ?string
    {
        return match (true) {
            is_string($rule) => $rule,
            is_array($rule) => $this->describeArrayForm($rule),
            $rule instanceof Closure => null,
            $rule instanceof Enum => 'enum',
            $rule instanceof Stringable => (string) $rule,
            is_object($rule) => Str::snake(class_basename($rule)),
            default => null,
        };
    }

    /** @param array<mixed> $rule */
    private function describeArrayForm(array $rule): string
    {
        $parts = array_map(fn (mixed $part): string => is_scalar($part) ? (string) $part : '', array_values($rule));
        $name = array_shift($parts) ?? '';

        return $parts === [] ? $name : $name.':'.implode(',', $parts);
    }
}
