<?php

namespace Lomkit\Rest\Fields;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
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
