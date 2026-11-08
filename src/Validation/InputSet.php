<?php

namespace Lomkit\Rest\Validation;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Rules\Distinct;

final readonly class InputSet
{
    private const array ENTRY_KEYS = ['name' => true, 'value' => true];

    public function __construct(private FieldSet $fields) {}

    public function positional(Cursor $parameters, ValidationContext $context): InputValues
    {
        $rules = $context->rules;
        $paths = [];

        foreach ($this->fields as $position => $field) {
            $paths[$field->name] = $parameters->at($position)->path;
            $rules->book()->field($parameters->at($position), $field);
        }

        $rules->addWhenPresent($parameters, ['array', 'list', 'max:'.count($this->fields)]);

        foreach ($this->fields as $field) {
            $rules->add($paths[$field->name], [...self::presence($field), ...RuleReferences::rewrite($field->getRules()->shared(), $paths)]);
        }

        $given = is_array($parameters->value) && array_is_list($parameters->value) ? $parameters->value : [];

        if ($parameters->present && $given !== $parameters->value) {
            $rules->partial();
        }

        return InputValues::fromPositional($this->fields, $given);
    }

    public function named(Cursor $fields, ValidationContext $context, string $list): InputValues
    {
        return $this->read($fields, $context, $list, false);
    }

    public function inputsFirst(Cursor $fields, ValidationContext $context, string $list): InputValues
    {
        return $this->read($fields, $context, $list, true);
    }

    private function read(Cursor $fields, ValidationContext $context, string $list, bool $inputsFirst): InputValues
    {
        $value = $fields->value ?? [];

        if ($context->reader->refused($fields) || ! is_array($value)) {
            return $this->partial($context);
        }

        if (! array_is_list($value)) {
            $context->rules->add($fields->path, ['list']);

            return $this->partial($context);
        }

        if ($inputsFirst) {
            $entries = $this->inputs($fields, $value, $context);
            $this->names($fields, $value, $list, $context);

            return InputValues::fromNamed($this->fields, $entries);
        }

        $this->names($fields, $value, $list, $context);

        return InputValues::fromNamed($this->fields, $this->inputs($fields, $value, $context));
    }

    /** @param list<mixed> $value */
    private function names(Cursor $fields, array $value, string $list, ValidationContext $context): void
    {
        $reader = $context->reader;
        $names = $context->vocabulary->of($context->resource, $context->grammar, $list, fn (): array => $this->fields->names());

        foreach ($reader->items($fields) as $entry) {
            $name = $entry->at('name');
            $reader->known($entry, self::ENTRY_KEYS);

            if (is_string($name->value)) {
                $context->rules->gate($fields->at($name->value), $name);
            }

            $context->rules->add($name->path, ['required', 'string', Distinct::among($value, 'name'), $names]);
        }
    }

    /**
     * @param  list<mixed>  $value
     * @return list<array{name: string, value?: mixed}>
     */
    private function inputs(Cursor $fields, array $value, ValidationContext $context): array
    {
        $paths = [];
        $entries = [];

        foreach ($this->fields as $field) {
            $paths[$field->name] = $fields->at($field->name)->path;
        }

        foreach ($value as $entry) {
            if (! is_array($entry) || ! is_string($entry['name'] ?? null) || ! isset($paths[$entry['name']])) {
                $context->rules->partial();

                continue;
            }

            if (array_key_exists('value', $entry)) {
                $context->rules->project($paths[$entry['name']], $entry['value']);
                $entries[] = ['name' => $entry['name'], 'value' => $entry['value']];
            }
        }

        $given = array_column($entries, 'name', 'name');

        foreach ($this->fields as $field) {
            $context->rules->book()->field($fields->at($field->name), $field);
            $context->rules->book()->label($paths[$field->name], self::label($field->name));
            $this->addInput($paths[$field->name], RuleReferences::rewrite($field->getRules()->shared(), $paths), isset($given[$field->name]), $context);
        }

        return $entries;
    }

    /** @param list<mixed> $rules */
    private function addInput(string $key, array $rules, bool $given, ValidationContext $context): void
    {
        if (! in_array('sometimes', $rules, true)) {
            $context->rules->add($key, $rules);

            return;
        }

        if ($given) {
            $context->rules->add($key, array_values(array_filter($rules, fn (mixed $rule): bool => $rule !== 'sometimes')));
        }
    }

    private function partial(ValidationContext $context): InputValues
    {
        $context->rules->partial();

        return InputValues::fromNamed($this->fields, []);
    }

    /** @return list<string> */
    private static function presence(Field $field): array
    {
        return match (true) {
            $field->hasDefault() && $field->isNullable() => [],
            $field->hasDefault() => ['filled'],
            $field->isNullable() => ['present'],
            default => ['required'],
        };
    }

    private static function label(string $name): string
    {
        $key = 'validation.attributes.'.$name;
        $line = Lang::get($key);

        return is_string($line) && $line !== $key ? $line : str_replace('_', ' ', Str::snake($name));
    }
}
