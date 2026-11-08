<?php

namespace Lomkit\Rest\Fields;

use BackedEnum;
use Closure;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Exceptions\InvalidFieldException;

class Enum extends Field
{
    /** @var class-string<BackedEnum>|null */
    private ?string $enum = null;

    /** @var list<mixed>|(Closure(BackedEnum): bool)|null */
    private array|Closure|null $offered = null;

    /** @param class-string<BackedEnum>|null $enum */
    #[\Override]
    public static function make(string $name, ?string $enum = null): static
    {
        $field = new static($name);
        $field->enum = $enum;

        return $field;
    }

    #[\Override]
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

    /** @param list<BackedEnum>|(Closure(BackedEnum): bool) $cases */
    public function offered(array|Closure $cases): static
    {
        $this->offered = $cases;

        return $this;
    }

    /** @return class-string<BackedEnum> */
    public function enumClass(): string
    {
        return $this->enum ?? throw InvalidFieldException::missingEnumClass($this->name);
    }

    /** @return list<int|string> */
    public function values(): array
    {
        return self::valuesOf($this->enumClass()::cases());
    }

    /** @return list<int|string> */
    public function offeredValues(): array
    {
        return self::valuesOf($this->offeredCases());
    }

    /** @return list<BackedEnum> */
    public function offeredCases(): array
    {
        $cases = $this->enumClass()::cases();

        if ($this->offered === null) {
            return $cases;
        }

        $offered = array_values(array_filter($cases, $this->offered instanceof Closure ? $this->offered : $this->listed($this->offered)));

        if ($offered === []) {
            throw InvalidFieldException::noOfferedCase($this->name);
        }

        return $offered;
    }

    public function offersEveryCase(): bool
    {
        return count($this->offeredCases()) === count($this->enumClass()::cases());
    }

    #[\Override]
    public function operators(): array
    {
        return ['=', '!=', 'in', 'not in'];
    }

    #[\Override]
    public function baseRules(): array
    {
        return [Rule::enum($this->enumClass())];
    }

    #[\Override]
    public function type(): string
    {
        return 'enum';
    }

    #[\Override]
    public function toSchema(): Type
    {
        return self::schemaOf($this->values());
    }

    #[\Override]
    public function toWriteSchema(): Type
    {
        return self::schemaOf($this->offeredValues());
    }

    #[\Override]
    protected function writeBaseRules(): array
    {
        if ($this->offered === null) {
            return $this->baseRules();
        }

        return [Rule::enum($this->enumClass())->only($this->offeredCases())];
    }

    #[\Override]
    protected function castValue(mixed $value): mixed
    {
        return match (true) {
            is_int($value), is_string($value) => $this->enumClass()::from($value),
            is_bool($value), is_float($value) => $this->enumClass()::from((int) $value),
            default => $value,
        };
    }

    /**
     * @param  list<mixed>  $cases
     * @return Closure(BackedEnum): bool
     */
    private function listed(array $cases): Closure
    {
        $class = $this->enumClass();

        foreach ($cases as $case) {
            if (! $case instanceof $class) {
                throw InvalidFieldException::foreignOfferedCase($this->name, $class, $case);
            }
        }

        return fn (BackedEnum $case): bool => in_array($case, $cases, true);
    }

    /**
     * @param  list<BackedEnum>  $cases
     * @return list<int|string>
     */
    private static function valuesOf(array $cases): array
    {
        return array_map(fn (BackedEnum $case): int|string => $case->value, $cases);
    }

    /** @param list<int|string> $values */
    private static function schemaOf(array $values): Type
    {
        return (array_all($values, fn (int|string $value): bool => is_int($value)) ? new IntegerType : new StringType)->enum($values);
    }
}
