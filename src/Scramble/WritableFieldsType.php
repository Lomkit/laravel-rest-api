<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Fields\WriteRules;

final readonly class WritableFieldsType
{
    private const string SHARED = 'all';

    /**
     * @param  array<string, list<string>>  $writable
     * @param  array<string, array<string, list<mixed>>>  $rules
     */
    private function __construct(private FieldSet $fields, private array $writable, private array $rules) {}

    /**
     * @param  array<string, ?WriteOperation>  $operations
     * @param  array<string, array<string, mixed>>  $operationRules
     * @param  array<string, mixed>  $sharedRules
     */
    public static function of(FieldSet $fields, array $operations, array $operationRules = [], array $sharedRules = []): self
    {
        $writable = [];
        $rules = [self::SHARED => array_map(WriteRules::listOf(...), $sharedRules)];

        foreach ($operations as $label => $operation) {
            $writable[$label] = $operation === null ? [] : $fields->writableOn($operation)->names();
            $rules[$label] = $operation === null ? [] : WriteRules::of($fields, $operation, $operationRules[$label] ?? [])->all();
        }

        return new self($fields, $writable, $rules);
    }

    public function toType(): ObjectType
    {
        $type = new ObjectType;
        $type->setDescription($this->description());

        foreach ($this->documented() as $name) {
            $type->addProperty($name, $this->property($name));
        }

        return $type;
    }

    private function description(): string
    {
        $sentences = [];

        foreach ($this->writable as $label => $names) {
            $sentences[] = 'Writable on '.$label.': '.($names === [] ? 'none' : implode(', ', $names)).'.';
        }

        return implode(' ', $sentences);
    }

    /** @return list<string> */
    private function documented(): array
    {
        return array_values(array_intersect($this->fields->names(), array_merge(...array_values($this->writable))));
    }

    private function property(string $name): Type
    {
        $rules = array_map(fn (array $rules): array => $rules[$name] ?? [], $this->rules);
        $type = RuleType::of(array_merge(...array_values($rules)));
        $described = [];

        foreach ($rules as $label => $list) {
            $strings = array_filter($list, is_string(...));

            if ($strings !== []) {
                $described[] = $label.': '.implode(', ', $strings);
            }
        }

        if ($described === []) {
            return $type;
        }

        return $type->setDescription(implode(' | ', $described));
    }
}
