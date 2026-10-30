<?php

namespace Lomkit\Rest\Tests\Unit\Scramble;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Validation\Rules\In;
use Lomkit\Rest\Scramble\RuleType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RuleTypeTest extends TestCase
{
    /**
     * @param  list<mixed>  $rules
     * @param  class-string  $type
     */
    #[Test]
    #[DataProvider('rules')]
    public function it_infers_the_type_that_the_rules_require(array $rules, string $type): void
    {
        $this->assertInstanceOf($type, RuleType::of($rules));
    }

    /** @return array<string, array{list<mixed>, class-string}> */
    public static function rules(): array
    {
        return [
            'integer' => [['integer'], IntegerType::class],
            'int' => [['int'], IntegerType::class],
            'numeric' => [['numeric'], NumberType::class],
            'decimal with places' => [['decimal:2'], NumberType::class],
            'boolean' => [['boolean'], BooleanType::class],
            'bool' => [['bool'], BooleanType::class],
            'array with keys' => [['array:name,username'], ArrayType::class],
            'string' => [['string'], StringType::class],
            'no rule' => [[], StringType::class],
            'in rule' => [[new In(['a', 'b'])], StringType::class],
            'integer next to a rule object' => [['nullable', new In([1, 2]), 'integer'], IntegerType::class],
            'integer in a pipe string' => [['nullable|integer|min:0'], IntegerType::class],
            'upper case' => [['Boolean'], BooleanType::class],
            'integer before numeric' => [['numeric', 'integer'], IntegerType::class],
        ];
    }
}
