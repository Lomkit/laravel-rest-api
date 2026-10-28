<?php

namespace Lomkit\Rest\Fields;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidFieldException;

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

    public function operators(): array
    {
        return ['=', '!=', 'in', 'not in'];
    }

    public function baseRules(): array
    {
        return match ($this->declaredKeyType ?? $this->boundKeyType) {
            'int' => ['integer'],
            'string' => ['string'],
            'uuid' => ['uuid'],
            'ulid' => ['ulid'],
        };
    }

    public function type(): string
    {
        return 'id';
    }

    protected function writableByDefault(): bool
    {
        return false;
    }
}
