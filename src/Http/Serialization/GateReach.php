<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Search\GateAbility;

final readonly class GateReach
{
    /** @param array<string, array<array-key, true>> $outside */
    private function __construct(private array $outside) {}

    public static function everyRow(): self
    {
        return new self([]);
    }

    public function reaches(GateAbility $gate, Model $model): bool
    {
        return ! isset($this->outside[$gate->name()][self::key($model->getKey())]);
    }

    private static function key(mixed $key): string
    {
        return is_scalar($key) ? (string) $key : '';
    }
}
