<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use stdClass;

final class UnencodableMeta
{
    /** @return array<string, array{mixed, string}> */
    public static function cases(): array
    {
        $loop = new stdClass;
        $loop->self = $loop;
        $deep = 'leaf';

        for ($level = 0; $level < 260; $level++) {
            $deep = [$deep];
        }

        return [
            'a string that is not UTF-8' => [['title' => "\xB1\x31"], 'Malformed UTF-8 characters, possibly incorrectly encoded'],
            'a key that is not UTF-8' => [["\xB1\x31" => 1], 'Malformed UTF-8 characters, possibly incorrectly encoded'],
            'a JsonSerializable that returns NAN' => [new NotFiniteValue(NAN), 'Inf and NaN cannot be JSON encoded'],
            'a JsonSerializable that returns INF' => [['ratio' => new NotFiniteValue(INF)], 'Inf and NaN cannot be JSON encoded'],
            'an object that holds itself' => [$loop, 'Recursion detected'],
            'a value nested too deep' => [$deep, 'Maximum stack depth exceeded'],
        ];
    }
}
