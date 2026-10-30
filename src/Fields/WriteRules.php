<?php

namespace Lomkit\Rest\Fields;

use Lomkit\Rest\Validation\RuleReferences;

final readonly class WriteRules
{
    /**
     * @param  array<string, list<mixed>>  $rules
     * @param  list<string>  $names
     */
    private function __construct(private array $rules, private array $names) {}

    /**
     * @param  array<array-key, mixed>  $operationRules
     * @param  array<array-key, mixed>  $sharedRules
     */
    public static function of(FieldSet $fields, WriteOperation $operation, array $operationRules = [], array $sharedRules = []): self
    {
        $rules = [];

        foreach ([$fields->rulesFor($operation), $operationRules, $sharedRules] as $source) {
            foreach ($source as $key => $list) {
                $rules[(string) $key] = [...($rules[(string) $key] ?? []), ...self::listOf($list)];
            }
        }

        return new self($rules, $fields->names());
    }

    /** @return list<mixed> */
    public static function listOf(mixed $rules): array
    {
        return match (true) {
            is_string($rules) => explode('|', $rules),
            is_array($rules) => array_values($rules),
            default => [$rules],
        };
    }

    /** @return array<string, list<mixed>> */
    public function all(): array
    {
        return $this->rules;
    }

    /** @return array<string, list<mixed>> */
    public function under(string $prefix): array
    {
        $paths = [];

        foreach ($this->names as $name) {
            $paths[$name] = $prefix.$name;
        }

        $rules = [];

        foreach ($this->rules as $key => $list) {
            $rules[$prefix.$key] = RuleReferences::rewrite($list, $paths);
        }

        return $rules;
    }
}
