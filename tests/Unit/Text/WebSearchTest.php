<?php

namespace Lomkit\Rest\Tests\Unit\Text;

use Lomkit\Rest\Text\TermPattern;
use Lomkit\Rest\Text\WebSearch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WebSearchTest extends TestCase
{
    #[Test]
    #[DataProvider('searches')]
    public function it_rebuilds_a_web_search_from_its_terms(string $value, string $postgres, string $mysql): void
    {
        $search = WebSearch::parse($value);

        $this->assertSame($postgres, $search->toPostgres());
        $this->assertSame($mysql, $search->toMySqlBoolean());
    }

    #[Test]
    public function it_keeps_the_underscores_inside_the_terms_of_a_mysql_search(): void
    {
        $search = WebSearch::parse('snake_case "x_y z" or abc_123 -__draft_', TermPattern::Underscored);

        $this->assertSame('+snake_case +("x_y z" abc_123) -draft', $search->toMySqlBoolean());
    }

    /** @return array<string, array{string, string, string}> */
    public static function searches(): array
    {
        return [
            'words' => ['desk lamp', 'desk lamp', '+desk +lamp'],
            'a phrase' => ['"desk lamp" brass', '"desk lamp" brass', '+"desk lamp" +brass'],
            'an excluded word' => ['lamp -floor', 'lamp -floor', '+lamp -floor'],
            'an excluded phrase' => ['lamp -"floor lamp"', 'lamp -"floor lamp"', '+lamp -"floor lamp"'],
            'alternatives' => ['desk or floor lamp', 'desk or floor lamp', '+(desk floor) +lamp'],
            'an alternative phrase' => ['"desk lamp" OR vase', '"desk lamp" or vase', '+("desk lamp" vase)'],
            'an or without left side' => ['or lamp', 'lamp', '+lamp'],
            'an or without right side' => ['lamp or', 'lamp', '+lamp'],
            'a word with punctuation' => ['x86-64 (lamp)', '"x86 64" lamp', '+"x86 64" +lamp'],
            'an unterminated quote' => ['lamp "desk', 'lamp desk', '+lamp +desk'],
            'operators of the databases' => ['+lamp* @2 <desk> ~vase & !oil | a:* \'b\'', 'lamp 2 desk vase oil a b', '+lamp +2 +desk +vase +oil +a +b'],
            'punctuation only' => ['-- "" - * ()', '', ''],
            'a quoted or' => ['lamp "or" vase', 'lamp "or" vase', '+lamp +or +vase'],
            'an or followed by punctuation' => ['lamp OR, vase', 'lamp "OR" vase', '+lamp +OR +vase'],
            'an excluded or' => ['lamp -or', 'lamp -"or"', '+lamp -or'],
        ];
    }
}
