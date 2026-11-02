<?php

namespace Lomkit\Rest\Fields;

final readonly class WriteRules
{
    /** @param array<string, list<mixed>> $rules */
    private function __construct(private array $rules, private FieldSet $fields) {}

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

        return new self($rules, $fields);
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

    public function fields(): FieldSet
    {
        return $this->fields;
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->fields->names();
    }
}
