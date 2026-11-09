<?php

namespace Lomkit\Rest\Fields;

use Carbon\Exceptions\InvalidFormatException;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DateTime extends Date
{
    private bool $converted = false;

    #[\Override]
    public function bind(Model $model): void
    {
        $this->converted = $this->convertedBy($model);
    }

    #[\Override]
    public function type(): string
    {
        return 'datetime';
    }

    #[\Override]
    public function toSchema(): Type
    {
        return (new StringType)->format('date-time');
    }

    #[\Override]
    protected function comparesByDay(Builder $query): bool
    {
        return false;
    }

    #[\Override]
    public function render(mixed $value): mixed
    {
        if ($this->converted || ! is_string($value)) {
            return $value;
        }

        try {
            return Carbon::parse($value)->toJSON();
        } catch (InvalidFormatException) {
            return $value;
        }
    }

    #[\Override]
    protected function normalize(Carbon $date): Carbon
    {
        return $date;
    }
}
