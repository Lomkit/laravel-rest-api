<?php

namespace Lomkit\Rest\Tests\Fixtures\Lab;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<mixed, mixed> */
class EncodedJson implements CastsAttributes
{
    /** @param array<string, mixed> $attributes */
    #[\Override]
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : null;
    }

    /** @param array<string, mixed> $attributes */
    #[\Override]
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
    }
}
