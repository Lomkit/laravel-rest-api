<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Lomkit\Rest\Tests\Fixtures\Festival\OpenFestivalPolicy;

#[Table('shows')]
#[UsePolicy(OpenFestivalPolicy::class)]
class SerializedShow extends UncastShow
{
    /** @return Attribute<string, never> */
    protected function featured(): Attribute
    {
        return Attribute::get(fn (mixed $value): string => $value ? 'yes' : 'no');
    }

    #[\Override]
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }
}
