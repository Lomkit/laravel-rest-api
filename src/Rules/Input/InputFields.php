<?php

namespace Lomkit\Rest\Rules\Input;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Validation\RuleCompiler;
use Lomkit\Rest\Validation\RuleReferences;

final class InputFields extends RestRule
{
    public function __construct(private readonly FieldSet $fields) {}

    #[\Override]
    public function compile(RuleCompiler $into, string $attribute, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        if (! array_is_list($value)) {
            $into->add([$attribute => ['list']]);

            return;
        }

        $paths = [];

        foreach ($this->fields as $field) {
            $paths[$field->name] = $attribute.'.'.$field->name;
        }

        foreach ($value as $entry) {
            if (is_array($entry) && is_string($entry['name'] ?? null) && isset($paths[$entry['name']]) && array_key_exists('value', $entry)) {
                $into->project($paths[$entry['name']], $entry['value']);
            }
        }

        foreach ($this->fields as $field) {
            $into->label($paths[$field->name], self::label($field->name));
            $into->add([$paths[$field->name] => RuleReferences::rewrite($field->getRules()->shared(), $paths)]);
        }
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        return [];
    }

    private static function label(string $name): string
    {
        $key = 'validation.attributes.'.$name;
        $line = Lang::get($key);

        return is_string($line) && $line !== $key ? $line : str_replace('_', ' ', Str::snake($name));
    }
}
