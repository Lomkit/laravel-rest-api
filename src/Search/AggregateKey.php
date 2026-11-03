<?php

namespace Lomkit\Rest\Search;

use Illuminate\Support\Str;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Field;

final readonly class AggregateKey
{
    private function __construct(public string $value) {}

    public static function of(string $relation, AggregateFunction $function, ?Field $field, ?string $alias): self
    {
        $name = sprintf('%s %s %s', $relation, $function->value, strtolower($field->name ?? '*'));

        return new self($alias ?? Str::snake((string) preg_replace('/[^[:alnum:][:space:]_]/u', '', $name)));
    }
}
