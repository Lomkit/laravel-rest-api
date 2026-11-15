<?php

namespace Lomkit\Rest\Tests\Unit\Authorization\Stubs;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<Settings, Settings> */
final class SettingsCast implements CastsAttributes
{
    /** @param array<string, mixed> $attributes */
    #[\Override]
    public function get(Model $model, string $key, mixed $value, array $attributes): Settings
    {
        $values = is_string($value) ? json_decode($value, true) : [];

        return new Settings(is_array($values) ? $values : []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    #[\Override]
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [$key => json_encode($value instanceof Settings ? $value->values : [], JSON_THROW_ON_ERROR)];
    }
}
