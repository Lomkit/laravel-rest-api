<?php

namespace Lomkit\Rest\Fields;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Exceptions\InvalidFieldException;

class Enum extends Field
{
    /** @var class-string<BackedEnum>|null */
    private ?string $enum = null;

    /** @param class-string<BackedEnum>|null $enum */
    public static function make(string $name, ?string $enum = null): static
    {
        $field = new static($name);
        $field->enum = $enum;

        return $field;
    }

    public function bind(Model $model): void
    {
        if ($this->enum !== null) {
            return;
        }

        $cast = $model->getCasts()[$this->name] ?? null;

        if (is_string($cast) && is_subclass_of($cast, BackedEnum::class)) {
            $this->enum = $cast;
        }
    }

    /** @return class-string<BackedEnum> */
    public function enumClass(): string
    {
        return $this->enum ?? throw InvalidFieldException::missingEnumClass($this->name);
    }

    /** @return list<int|string> */
    public function values(): array
    {
        return array_map(fn (BackedEnum $case): int|string => $case->value, $this->enumClass()::cases());
    }

    public function operators(): array
    {
        return ['=', '!=', 'in', 'not in'];
    }

    public function baseRules(): array
    {
        return [Rule::enum($this->enumClass())];
    }

    public function type(): string
    {
        return 'enum';
    }
}
