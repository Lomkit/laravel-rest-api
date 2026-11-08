<?php

namespace Lomkit\Rest\Actions\Rows;

use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Support\Translation;

final readonly class RowCap
{
    public function __construct(private int $max) {}

    public static function of(?int $max): ?self
    {
        return $max === null ? null : new self($max);
    }

    public function limit(?int $limit): int
    {
        return min($limit ?? PHP_INT_MAX, $this->max + 1);
    }

    public function check(int $count): void
    {
        if ($count > $this->max) {
            throw self::refusal($this->max);
        }
    }

    public static function refusal(int $max): ValidationException
    {
        return ValidationException::withMessages(['search' => [Translation::line('rest::actions.row_cap', ['max' => (string) $max])]]);
    }
}
