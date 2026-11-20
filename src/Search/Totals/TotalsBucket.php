<?php

namespace Lomkit\Rest\Search\Totals;

use JsonSerializable;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Text;

use function Illuminate\Support\enum_value;

/** @internal */
final readonly class TotalsBucket implements JsonSerializable
{
    public function __construct(
        public int|string|bool|null $value,
        public int $count,
    ) {}

    public static function read(Field $field, mixed $value, int $count): self
    {
        $cast = match (true) {
            $value === null => null,
            $field instanceof Enum && (is_int($value) || is_string($value)) => $field->enumClass()::tryFrom($value)->value ?? $value,
            $field instanceof Text && is_scalar($value) => (string) $value,
            default => enum_value($field->cast($value)),
        };

        return new self(is_int($cast) || is_string($cast) || is_bool($cast) ? $cast : null, $count);
    }

    /** @return array{value: int|string|bool|null, count: int} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return ['value' => $this->value, 'count' => $this->count];
    }
}
