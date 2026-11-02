<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class UnconvertedBlankInputsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([ConvertEmptyStringsToNull::class, TrimStrings::class]);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('blankTypedValues')]
    public function it_asks_for_a_value_on_a_blank_value_of_a_type_that_cannot_hold_it(array $search, string $key, string $message): void
    {
        $response = $this->postJson('/api/posts/search', ['search' => $search]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function blankTypedValues(): array
    {
        $parameter = 'search.scopes.0.parameters.0';
        $value = 'search.filters.0.value';

        return [
            'an integer parameter' => [['scopes' => [['name' => 'viewedBetween', 'parameters' => ['', 5]]]], $parameter, "The {$parameter} field is required."],
            'a date parameter' => [['scopes' => [['name' => 'publishedSince', 'parameters' => ['  ']]]], $parameter, "The {$parameter} field is required."],
            'an enum parameter' => [['scopes' => [['name' => 'reaching', 'parameters' => ['']]]], $parameter, "The {$parameter} field is required."],
            'a boolean instruction field' => [
                ['instructions' => [['name' => 'highlight-posts', 'fields' => [['name' => 'featured', 'value' => '']]]]],
                'search.instructions.0.fields.featured',
                'The featured field must have a value.',
            ],
            'an enum instruction field' => [
                ['instructions' => [['name' => 'in-status', 'fields' => [['name' => 'status', 'value' => ' ']]]]],
                'search.instructions.0.fields.status',
                'The status field is required.',
            ],
            'an integer filter value' => [['filters' => [['field' => 'views', 'value' => '']]], $value, "The {$value} field is required."],
            'a date filter value' => [['filters' => [['field' => 'published_on', 'operator' => '>', 'value' => '  ']]], $value, "The {$value} field is required."],
            'the list of an in filter on a text' => [['filters' => [['field' => 'title', 'operator' => 'in', 'value' => '']]], $value, "The {$value} field must have a value."],
            'the list of a between filter' => [['filters' => [['field' => 'views', 'operator' => 'between', 'value' => ' ']]], $value, "The {$value} field must have a value."],
            'an integer in the list of an in filter' => [
                ['filters' => [['field' => 'views', 'operator' => 'in', 'value' => [1, '']]]],
                "{$value}.1",
                "The {$value}.1 field is required.",
            ],
        ];
    }

    /** @param array<string, mixed> $filter */
    #[Test]
    #[DataProvider('blankTextValues')]
    public function it_leaves_a_blank_text_value_to_the_rules_of_its_field(array $filter, string $key): void
    {
        $message = "The {$key} field is required.";

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [$filter]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function blankTextValues(): array
    {
        return [
            'a compared text' => [['field' => 'title', 'value' => ''], 'search.filters.0.value'],
            'a text in the list of an in filter' => [['field' => 'title', 'operator' => 'in', 'value' => ['Annual report', ' ']], 'search.filters.0.value.1'],
        ];
    }

    #[Test]
    public function it_leaves_a_blank_value_to_a_filter_that_takes_none(): void
    {
        $unrated = Post::factory()->create(['id' => 1]);
        Post::factory()->create(['id' => 2, 'rating' => 4.5]);

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'rating', 'operator' => 'is null', 'value' => '']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [$unrated->id]);
    }
}
