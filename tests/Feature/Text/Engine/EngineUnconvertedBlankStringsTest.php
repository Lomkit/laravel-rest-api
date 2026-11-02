<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineUnconvertedBlankStringsTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([ConvertEmptyStringsToNull::class, TrimStrings::class]);
        config(['scout.driver' => 'collection']);
    }

    /** @param array<string, mixed> $filter */
    #[Test]
    #[DataProvider('blankFilterKeys')]
    public function it_asks_for_a_value_on_a_blank_key_of_an_engine_filter(array $filter, string $key): void
    {
        $message = "The {$key} field must have a value.";

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [$filter]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function blankFilterKeys(): array
    {
        return [
            'a blank operator' => [['field' => 'number', 'operator' => ' ', 'value' => 3], 'search.filters.0.operator'],
            'a blank type' => [['field' => 'number', 'value' => 3, 'type' => ''], 'search.filters.0.type'],
            'the blank list of an in filter on a text' => [['field' => 'code', 'operator' => 'in', 'value' => ''], 'search.filters.0.value'],
        ];
    }

    /** @param array<string, mixed> $filter */
    #[Test]
    #[DataProvider('requiredFilterKeys')]
    public function it_keeps_the_required_message_on_a_blank_required_key_of_an_engine_filter(array $filter, string $key): void
    {
        $message = "The {$key} field is required.";

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [$filter]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function requiredFilterKeys(): array
    {
        return [
            'a blank field' => [['field' => '', 'value' => 3], 'search.filters.0.field'],
            'a blank integer' => [['field' => 'number', 'value' => '  '], 'search.filters.0.value'],
            'a blank integer in the list of an in filter' => [['field' => 'number', 'operator' => 'in', 'value' => [3, '']], 'search.filters.0.value.1'],
        ];
    }

    /** @param array<string, mixed> $filter */
    #[Test]
    #[DataProvider('blankTextValues')]
    public function it_leaves_a_blank_text_value_to_the_rules_of_its_field(array $filter, string $key): void
    {
        $message = "The {$key} field is required.";

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [$filter]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function blankTextValues(): array
    {
        return [
            'a compared text' => [['field' => 'code', 'value' => ''], 'search.filters.0.value'],
            'a text in the list of an in filter' => [['field' => 'code', 'operator' => 'in', 'value' => ['V-a', ' ']], 'search.filters.0.value.1'],
        ];
    }

    #[Test]
    #[DataProvider('engineSearches')]
    public function it_asks_for_a_value_on_a_blank_trashed_value_the_engine_does_not_take(string $uri): void
    {
        config(['scout.soft_delete' => false]);
        $message = 'The search.trashed field must have a value.';

        $response = $this->postJson($uri, ['search' => ['text' => ['value' => 'report'], 'trashed' => '']]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.trashed' => [$message]]]);
    }

    /** @return array<string, array{string}> */
    public static function engineSearches(): array
    {
        return [
            'a search' => ['/api/vouchers/search'],
            'the search of an action' => ['/api/vouchers/actions/deactivate-vouchers'],
        ];
    }
}
