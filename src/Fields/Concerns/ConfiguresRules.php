<?php

namespace Lomkit\Rest\Fields\Concerns;

use Lomkit\Rest\Fields\WriteOperation;

trait ConfiguresRules
{
    private bool $nullable = false;

    /** @var list<mixed> */
    private array $rules = [];

    /** @var list<mixed> */
    private array $creationRules = [];

    /** @var list<mixed> */
    private array $updateRules = [];

    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function rules(mixed ...$rules): static
    {
        $this->rules = [...$this->rules, ...self::flattenRules($rules)];

        return $this;
    }

    public function creationRules(mixed ...$rules): static
    {
        $this->creationRules = [...$this->creationRules, ...self::flattenRules($rules)];

        return $this;
    }

    public function updateRules(mixed ...$rules): static
    {
        $this->updateRules = [...$this->updateRules, ...self::flattenRules($rules)];

        return $this;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /** @return list<mixed> */
    public function rulesFor(WriteOperation $operation): array
    {
        return [...$this->ruleSets()['all'], ...$this->ruleSets()[$operation->value]];
    }

    /** @return array{all: list<mixed>, create: list<mixed>, update: list<mixed>} */
    public function ruleSets(): array
    {
        return [
            'all' => [...($this->nullable ? ['nullable'] : []), ...$this->baseRules(), ...$this->rules],
            'create' => $this->creationRules,
            'update' => $this->updateRules,
        ];
    }

    /**
     * @param  array<mixed>  $rules
     * @return list<mixed>
     */
    private static function flattenRules(array $rules): array
    {
        $flat = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                array_push($flat, ...explode('|', $rule));

                continue;
            }

            if (is_array($rule)) {
                array_push($flat, ...array_values($rule));

                continue;
            }

            $flat[] = $rule;
        }

        return $flat;
    }
}
