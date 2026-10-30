<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;

final class RuleType
{
    /** @param list<mixed> $rules */
    public static function of(array $rules): Type
    {
        $names = self::names($rules);

        return match (true) {
            array_intersect(['integer', 'int'], $names) !== [] => new IntegerType,
            array_intersect(['numeric', 'decimal'], $names) !== [] => new NumberType,
            array_intersect(['boolean', 'bool'], $names) !== [] => new BooleanType,
            in_array('array', $names, true) => new ArrayType,
            default => new StringType,
        };
    }

    /**
     * @param  list<mixed>  $rules
     * @return list<string>
     */
    private static function names(array $rules): array
    {
        $names = [];

        foreach (array_filter($rules, is_string(...)) as $rule) {
            foreach (explode('|', $rule) as $token) {
                $names[] = strtolower(trim(explode(':', $token)[0]));
            }
        }

        return $names;
    }
}
