<?php

namespace Lomkit\Rest\Tests\Unit\Search;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Search\Totals\MeasureNode;
use Lomkit\Rest\Search\Totals\MeasureType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MeasureNodeTest extends TestCase
{
    #[Test]
    #[DataProvider('values')]
    public function it_types_a_measure_like_its_field(MeasureNode $measure, mixed $raw, int|float|null $expected): void
    {
        $this->assertSame($expected, $measure->value($raw));
    }

    /** @return array<string, array{MeasureNode, mixed, int|float|null}> */
    public static function values(): array
    {
        $seats = Integer::make('seats');
        $price = Number::make('price');

        return [
            'an integer sum above 2^53 read as a decimal string' => [new MeasureNode(MeasureType::Sum, $seats), '9007199254740993', 9007199254740993],
            'a negative integer sum read as a string' => [new MeasureNode(MeasureType::Sum, $seats), '-9007199254740993', -9007199254740993],
            'an integer maximum read with a fraction' => [new MeasureNode(MeasureType::Max, $seats), '300.000', 300],
            'an integer average' => [new MeasureNode(MeasureType::Avg, $seats), '150.5000', 150.5],
            'a number sum' => [new MeasureNode(MeasureType::Sum, $price), '43.00', 43.0],
            'a sum without rows' => [new MeasureNode(MeasureType::Sum, $seats), null, 0],
            'a number sum without rows' => [new MeasureNode(MeasureType::Sum, $price), null, 0.0],
            'a minimum without rows' => [new MeasureNode(MeasureType::Min, $price), null, null],
        ];
    }
}
