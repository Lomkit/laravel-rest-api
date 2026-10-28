<?php

namespace Lomkit\Rest\Fields;

final readonly class InputValues
{
    /** @param array<string, mixed> $given */
    private function __construct(private FieldSet $fields, private array $given) {}

    /** @param list<mixed> $parameters */
    public static function fromPositional(FieldSet $fields, array $parameters): self
    {
        $given = [];

        foreach ($fields->names() as $position => $name) {
            if (array_key_exists($position, $parameters)) {
                $given[$name] = $parameters[$position];
            }
        }

        return new self($fields, $given);
    }

    /** @param list<array{name: string, value?: mixed}> $entries */
    public static function fromNamed(FieldSet $fields, array $entries): self
    {
        $given = [];

        foreach ($entries as $entry) {
            if (array_key_exists('value', $entry)) {
                $given[$entry['name']] = $entry['value'];
            }
        }

        return new self($fields, $given);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $values = [];

        foreach ($this->fields as $field) {
            if (array_key_exists($field->name, $this->given)) {
                $values[$field->name] = $field->cast($this->given[$field->name]);

                continue;
            }

            if ($field->hasDefault()) {
                $values[$field->name] = $field->cast($field->defaultValue());
            }
        }

        return $values;
    }

    /** @return list<mixed> */
    public function arguments(): array
    {
        $values = $this->toArray();
        $arguments = [];

        foreach ($this->fields->names() as $name) {
            if (! array_key_exists($name, $values)) {
                break;
            }

            $arguments[] = $values[$name];
        }

        return $arguments;
    }
}
