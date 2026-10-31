<?php

namespace Lomkit\Rest\Tests\Unit\Text;

use Lomkit\Rest\Text\TermPattern;
use Lomkit\Rest\Text\TextMode;
use Lomkit\Rest\Text\TextQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TextQueryTest extends TestCase
{
    #[Test]
    public function it_parses_the_value_and_the_mode_of_a_search_text(): void
    {
        $query = TextQuery::fromSearch(['value' => 'desk lamp', 'mode' => 'phrase']);

        $this->assertEquals(new TextQuery('desk lamp', TextMode::Phrase), $query);
    }

    #[Test]
    public function it_searches_words_by_default(): void
    {
        $this->assertSame(TextMode::Words, TextQuery::fromSearch(['value' => 'lamp'])?->mode);
    }

    #[Test]
    #[DataProvider('blankTexts')]
    public function it_reads_no_query_from_a_blank_text(mixed $text): void
    {
        $this->assertNull(TextQuery::fromSearch($text));
    }

    /** @return array<string, array{mixed}> */
    public static function blankTexts(): array
    {
        return [
            'no text' => [null],
            'a text that is not an array' => ['lamp'],
            'no value' => [[]],
            'a null value' => [['value' => null]],
            'an empty value' => [['value' => '']],
            'a value made of spaces' => [['value' => " \t\n"]],
            'a value that is not a string' => [['value' => ['lamp']]],
        ];
    }

    #[Test]
    public function it_splits_the_value_into_letter_and_digit_terms(): void
    {
        $query = new TextQuery("Café-crème \"2026\" +l'été* (x86_64) ~ä́");

        $this->assertSame(['Café', 'crème', '2026', 'l', 'été', 'x86', '64', 'ä́'], $query->terms());
        $this->assertTrue($query->hasTerms());
    }

    #[Test]
    public function it_keeps_the_underscores_between_letters_and_digits_with_the_underscored_pattern(): void
    {
        $query = new TextQuery('snake_case ABC_123 _lead trail_ a__b x86_64 __ é_1');

        $this->assertSame(['snake_case', 'ABC_123', 'lead', 'trail', 'a__b', 'x86_64', 'é_1'], $query->terms(TermPattern::Underscored));
        $this->assertSame(['snake', 'case', 'ABC', '123', 'lead', 'trail', 'a', 'b', 'x86', '64', 'é', '1'], $query->terms());
    }

    #[Test]
    public function it_has_no_terms_in_a_value_made_of_punctuation(): void
    {
        $this->assertFalse((new TextQuery('!!! -- "" %_*'))->hasTerms());
    }

    #[Test]
    public function it_keeps_the_words_that_hold_a_letter_or_a_digit(): void
    {
        $query = new TextQuery("  50%  off - o'brien  ** ");

        $this->assertSame(['50%', 'off', "o'brien"], $query->words());
        $this->assertSame("50% off - o'brien **", $query->phrase());
    }
}
