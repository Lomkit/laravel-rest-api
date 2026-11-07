<?php

namespace Lomkit\Rest\Actions\Rows;

use Illuminate\Validation\ValidationException;

final readonly class RowCap
{
    public function __construct(private int $max) {}

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
        return ValidationException::withMessages(['search' => ["This action can be run on at most {$max} resources; narrow the search."]]);
    }
}
