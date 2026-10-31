<?php

namespace Lomkit\Rest\Tests\Fixtures\Lab;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\AsFluent;
use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[UsePolicy(OpenLabPolicy::class)]
class Recorder extends Model
{
    public $timestamps = false;

    /** @return Attribute<mixed, mixed> */
    protected function notes(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): mixed => $value === null ? null : json_decode($value, true, flags: JSON_THROW_ON_ERROR),
            set: fn (mixed $value): ?string => $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'profile' => AsFluent::class,
            'samples' => AsVector::class,
            'limits' => EncodedJson::class,
            'transcript' => AsStringable::class,
        ];
    }
}
