<?php

namespace Lomkit\Rest\Fields;

use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Stringable;

class ID extends Field
{
    /** @var 'int'|'string'|'uuid'|'ulid'|null */
    private ?string $declaredKeyType = null;

    /** @var 'int'|'string'|'uuid'|'ulid' */
    private string $boundKeyType = 'int';

    public function keyType(string $keyType): static
    {
        $this->declaredKeyType = match ($keyType) {
            'int', 'string', 'uuid', 'ulid' => $keyType,
            default => throw InvalidFieldException::unknownKeyType($this->name, $keyType),
        };

        return $this;
    }

    #[\Override]
    public function bind(Model $model): void
    {
        $traits = class_uses_recursive($model);

        $this->boundKeyType = match (true) {
            in_array(HasUuids::class, $traits, true) && in_array($this->name, $model->uniqueIds(), true) => 'uuid',
            in_array(HasUlids::class, $traits, true) && in_array($this->name, $model->uniqueIds(), true) => 'ulid',
            in_array($model->getKeyType(), ['int', 'integer'], true) => 'int',
            default => 'string',
        };
    }

    #[\Override]
    public function operators(): array
    {
        return ['=', '!=', 'in', 'not in'];
    }

    #[\Override]
    public function baseRules(): array
    {
        return match ($this->resolvedKeyType()) {
            'int' => ['integer'],
            'string' => ['string'],
            'uuid' => ['uuid'],
            'ulid' => ['ulid'],
        };
    }

    #[\Override]
    public function type(): string
    {
        return 'id';
    }

    #[\Override]
    public function toSchema(): Type
    {
        return match ($this->resolvedKeyType()) {
            'int' => new IntegerType,
            'uuid' => (new StringType)->format('uuid'),
            'string', 'ulid' => new StringType,
        };
    }

    public function parseKey(mixed $value): int|string|null
    {
        return match ($this->resolvedKeyType()) {
            'int' => is_int($key = filter_var($value, FILTER_VALIDATE_INT)) ? $key : null,
            'string' => is_string($value) ? $value : null,
            'uuid' => is_string($value) && Str::isUuid($value) ? $value : null,
            'ulid' => is_string($value) && Str::isUlid($value) ? $value : null,
        };
    }

    public function normalizeKey(int|string $key): int|string
    {
        return in_array($this->resolvedKeyType(), ['uuid', 'ulid'], true) ? strtolower((string) $key) : $key;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<int|string>  $keys
     * @return Builder<TModel>
     */
    public function whereKeys(Builder $query, array $keys): Builder
    {
        $column = $query->getModel()->getQualifiedKeyName();

        if ($this->resolvedKeyType() === 'int') {
            return $query->whereIntegerInRaw($column, $keys);
        }

        return $query->whereIn($column, $keys);
    }

    /** @return 'int'|'string'|'uuid'|'ulid' */
    public function resolvedKeyType(): string
    {
        return $this->declaredKeyType ?? $this->boundKeyType;
    }

    #[\Override]
    protected function castValue(mixed $value): mixed
    {
        if ($this->resolvedKeyType() === 'int') {
            return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $value;
        }

        return is_scalar($value) || $value instanceof Stringable ? (string) $value : $value;
    }

    #[\Override]
    protected function writableByDefault(): bool
    {
        return false;
    }
}
