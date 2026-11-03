<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineSortGrammarTest extends EngineSearchTestCase
{
    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('databaseSorts')]
    public function it_refuses_a_relation_or_aggregate_sort_next_to_an_engine_text(array $search): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], ...$search]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.sorts.0.field' => ['The selected search.sorts.0.field is invalid.']]);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function databaseSorts(): array
    {
        return [
            'a relation path' => [['sorts' => [['field' => 'lines.label']]]],
            'the key of an aggregate' => [['aggregates' => [['relation' => 'lines', 'type' => 'count']], 'sorts' => [['field' => 'lines_count']]]],
        ];
    }

    #[Test]
    public function it_sorts_on_the_key_of_an_aggregate_without_a_text(): void
    {
        $response = $this->search('vouchers', [
            'aggregates' => [['relation' => 'lines', 'type' => 'count']],
            'sorts' => [['field' => 'lines_count', 'direction' => 'desc'], ['field' => 'number']],
            'selects' => [['field' => 'id']],
        ]);

        $response->assertOk()->assertJsonPath('data.*.id', [1, 3, 5, 2]);
    }
}
