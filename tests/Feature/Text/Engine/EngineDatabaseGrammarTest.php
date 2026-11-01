<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineDatabaseGrammarTest extends EngineSearchTestCase
{
    private RecordingCollectionEngine $engine;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RecordingCollectionEngine;
        $this->useEngine($this->engine);
    }

    #[Test]
    public function it_keeps_the_database_grammar_for_a_search_without_terms(): void
    {
        $search = [
            'filters' => [
                ['field' => 'title', 'operator' => 'like', 'value' => 'Report%'],
                ['field' => 'price', 'operator' => '<', 'value' => 5, 'type' => 'or'],
            ],
            'scopes' => [['name' => 'cheap']],
            'sorts' => [['field' => 'title', 'direction' => 'desc']],
        ];

        $blank = $this->search('vouchers', ['text' => ['value' => ' '], ...$search]);
        $punctuation = $this->search('vouchers', ['text' => ['value' => '%_!'], ...$search]);
        $absent = $this->search('vouchers', $search);

        $blank->assertOk()->assertExactJson($this->page([3, 1], 2));
        $punctuation->assertOk()->assertExactJson($this->page([3, 1], 2));
        $absent->assertOk()->assertExactJson($this->page([3, 1], 2));
        $this->assertSame([], $this->engine->searches);
    }

    #[Test]
    public function it_refuses_a_text_on_a_resource_without_text_search(): void
    {
        $response = $this->search('voucher-lines', ['text' => ['value' => 'l1a']]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.text field is prohibited.',
            'errors' => ['search.text' => ['The search.text field is prohibited.']],
        ]);
    }

    #[Test]
    public function it_keeps_the_database_grammar_in_an_include_of_a_resource_searched_on_an_engine(): void
    {
        $response = $this->search('voucher-lines', [
            'filters' => [['field' => 'label', 'value' => 'l1a'], ['field' => 'label', 'value' => 'l3a', 'type' => 'or']],
            'includes' => [['relation' => 'voucher', 'filters' => [
                ['field' => 'title', 'operator' => 'like', 'value' => '%beta'],
                ['field' => 'price', 'operator' => '<', 'value' => 2, 'type' => 'or'],
            ]]],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [3, 1])
            ->assertJsonPath('data.*.voucher.id', [3, 1]);
        $this->assertSame([], $this->engine->searches);
    }

    /** @param array<string, mixed> $text */
    #[Test]
    #[DataProvider('emptyIncludeTexts')]
    public function it_loads_an_include_that_holds_an_empty_text_without_its_text_search(array $text): void
    {
        $response = $this->search('vouchers', [
            'text' => ['value' => 'report'],
            'includes' => [['relation' => 'lines', 'includes' => [['relation' => 'voucher', ...$text]]]],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [5, 1, 3])
            ->assertJsonPath('data.*.lines.*.voucher.id', [5, 1, 1, 3]);
        $this->assertCount(1, $this->engine->searches);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function emptyIncludeTexts(): array
    {
        return [
            'a null text' => [['text' => null]],
            'an empty object' => [['text' => []]],
            'an empty string' => [['text' => '']],
        ];
    }

    #[Test]
    public function it_acts_on_every_chunk_of_an_action_whose_include_holds_an_empty_text(): void
    {
        $received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        Voucher::query()->create(['id' => 7, 'code' => 'V-g', 'number' => 7, 'team' => 'red', 'title' => 'Report green', 'price' => 9, 'active' => true]);
        Voucher::query()->create(['id' => 8, 'code' => 'V-h', 'number' => 8, 'team' => 'red', 'title' => 'Report hazel', 'price' => 11, 'active' => true]);

        $response = $this->postJson('/api/vouchers/actions/retitle-vouchers', ['search' => [
            'text' => ['value' => 'report'],
            'includes' => [['relation' => 'lines', 'includes' => [['relation' => 'voucher', 'text' => null]]]],
        ]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 5]]);
        $this->assertSame([['handle', ['ids' => [8, 7]]], ['handle', ['ids' => [5]]], ['handle', ['ids' => [1, 3]]]], $received->calls());
        $this->assertSame(
            ['Renamed V-c', 'Budget', 'Renamed V-a', 'Report blue', 'Renamed V-e', 'Report trashed', 'Renamed V-g', 'Renamed V-h'],
            Voucher::withTrashed()
                ->orderBy('id')
                ->pluck('title')
                ->all(),
        );
    }
}
