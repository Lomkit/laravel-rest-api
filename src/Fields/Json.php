<?php

namespace Lomkit\Rest\Fields;

use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Database\Eloquent\Model;

class Json extends Field
{
    private const array STRING_CASTS = ['string', 'encrypted', AsStringable::class];

    private bool $encodes = false;

    #[\Override]
    public function bind(Model $model): void
    {
        $this->encodes = ! $model->hasSetMutator($this->name)
            && ! $model->hasAttributeSetMutator($this->name)
            && (! $model->hasCast($this->name) || $model->hasCast($this->name, self::STRING_CASTS));
    }

    #[\Override]
    public function toStorage(mixed $value): mixed
    {
        if (! $this->encodes || $value === null) {
            return $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    #[\Override]
    public function fromStorage(mixed $value): mixed
    {
        if (! $this->encodes || ! is_string($value)) {
            return $value;
        }

        return json_decode($value, true);
    }

    #[\Override]
    public function operators(): array
    {
        return [];
    }

    #[\Override]
    public function baseRules(): array
    {
        return ['array'];
    }

    #[\Override]
    public function type(): string
    {
        return 'json';
    }

    #[\Override]
    protected function sortableByDefault(): bool
    {
        return false;
    }

    #[\Override]
    protected function filterableByDefault(): bool
    {
        return false;
    }
}
