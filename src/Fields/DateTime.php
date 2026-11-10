<?php

namespace Lomkit\Rest\Fields;

use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DateTime extends Date
{
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
    public function modelCast(): ?string
    {
        return 'datetime';
    }

    #[\Override]
    protected function comparesByDay(Builder $query): bool
    {
        return false;
    }

    #[\Override]
    protected function normalize(Carbon $date): Carbon
    {
        return $date;
    }
}
