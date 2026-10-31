<?php

namespace Lomkit\Rest\Tests\Unit\Text;

use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Text\Engine\EngineGrammar;
use Lomkit\Rest\Text\Engine\IndexAttribute;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EngineGrammarDetailsTest extends TestCase
{
    #[Test]
    #[DataProvider('limits')]
    public function it_describes_the_limits_of_an_engine(int $sortKeys, ?int $maxPageSize, ?int $window, string $expected): void
    {
        $this->assertSame($expected, json_encode(new EngineGrammar([], [], $sortKeys, $maxPageSize, $window)));
    }

    /** @return array<string, array{int, int|null, int|null, string}> */
    public static function limits(): array
    {
        return [
            'a bounded number of sorts and page size' => [3, 250, null, '{"filters":[],"sorts":[],"max_sorts":3,"max_page_size":250,"window":null}'],
            'relevance only and a result window' => [0, null, 10000, '{"filters":[],"sorts":[],"max_sorts":0,"max_page_size":null,"window":10000}'],
            'no limit' => [PHP_INT_MAX, null, null, '{"filters":[],"sorts":[],"max_sorts":null,"max_page_size":null,"window":null}'],
        ];
    }

    #[Test]
    public function it_lists_the_values_of_an_enum_attribute(): void
    {
        $attribute = new IndexAttribute(Enum::make('status', PostStatus::class), [FilterOperator::Equal, FilterOperator::In]);

        $this->assertSame('{"name":"status","type":"enum","operators":["=","in"],"values":["draft","review","published"]}', json_encode($attribute));
    }

    #[Test]
    public function it_lists_the_filters_in_declaration_order(): void
    {
        $grammar = new EngineGrammar([
            'price' => new IndexAttribute(Number::make('price'), [FilterOperator::GreaterThan]),
            'code' => new IndexAttribute(Text::make('code'), [FilterOperator::Equal]),
        ], []);

        $this->assertSame(
            '{"filters":[{"name":"price","type":"number","operators":[">"]},{"name":"code","type":"text","operators":["="]}],"sorts":[],"max_sorts":null,"max_page_size":null,"window":null}',
            json_encode($grammar),
        );
    }
}
