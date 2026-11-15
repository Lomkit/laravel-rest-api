<?php

namespace Lomkit\Rest\Tests\Unit\Authorization\Stubs;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** @implements CastsAttributes<Collection<string, mixed>, Collection<string, mixed>> */
final class LabelCast implements CastsAttributes
{
    public static int $writes = 0;

    /** @param array<string, mixed> $attributes */
    #[\Override]
    public function get(Model $model, string $key, mixed $value, array $attributes): Collection
    {
        $code = is_string($value) ? explode('|', $value)[0] : '';

        return new Collection(['code' => $code, 'owner' => $attributes['owner']]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    #[\Override]
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $code = $value instanceof Collection ? $value->get('code') : $value;
        self::$writes++;

        return [$key => (is_string($code) ? $code : '').'|'.self::$writes];
    }
}
