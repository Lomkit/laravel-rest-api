<?php

namespace Lomkit\Rest\Fields;

use DateTimeInterface;
use DateTimeZone;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class DateTime extends Date
{
    private const string STORED_FORMAT = 'Y-m-d H:i:s';

    private const string STORED_FORMAT_WITH_MICROSECONDS = 'Y-m-d H:i:s.u';

    final public static function storedZone(): DateTimeZone
    {
        return new DateTimeZone(Config::string('app.timezone'));
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
    public function modelCast(): ?string
    {
        return 'datetime';
    }

    #[\Override]
    public function toStorage(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $this->toDate($value);
        }

        if (! is_string($value)) {
            return $value;
        }

        $date = $this->toDate($value);

        return $date->format($date->micro === 0 ? self::STORED_FORMAT : self::STORED_FORMAT_WITH_MICROSECONDS);
    }

    #[\Override]
    protected function comparesByDay(Builder $query): bool
    {
        return false;
    }

    #[\Override]
    protected function normalize(Carbon $date): Carbon
    {
        return $date->setTimezone(self::storedZone());
    }
}
