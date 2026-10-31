<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ContainsVoucherController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineDetailsTest extends EngineTestCase
{
    private const string GRAMMAR = '{"filters":['
        .'{"name":"number","type":"integer","operators":["=","!=",">",">=","<","<=","in","not in"]},'
        .'{"name":"price","type":"number","operators":["=","!=",">",">=","<","<=","in","not in"]},'
        .'{"name":"active","type":"boolean","operators":["=","!="]},'
        .'{"name":"code","type":"text","operators":["=","!=","in","not in"]}'
        .'],"sorts":["price","number"],"max_sorts":null,"max_page_size":null,"window":null}';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config(['scout.driver' => 'collection']);
        Route::prefix('api')->group(fn () => Rest::resource('contains-vouchers', ContainsVoucherController::class)->register());
    }

    #[Test]
    #[DataProvider('trashed')]
    public function it_describes_the_grammar_of_a_search_engine(bool $softDelete, string $expected): void
    {
        config(['scout.soft_delete' => $softDelete]);

        $this->assertTextDetails($expected, $this->getJson('/api/vouchers'));
    }

    /** @return array<string, array{bool, string}> */
    public static function trashed(): array
    {
        return [
            'an index that keeps trashed models' => [true, '{"fields":[],"modes":["words"],"relevance":true,"trashed":true,"max_length":255,"engine":'.self::GRAMMAR.'}'],
            'an index without trashed models' => [false, '{"fields":[],"modes":["words"],"relevance":true,"trashed":false,"max_length":255,"engine":'.self::GRAMMAR.'}'],
        ];
    }

    #[Test]
    public function it_leaves_the_perimeter_attributes_out_of_the_grammar(): void
    {
        $response = $this->getJson('/api/team-vouchers');

        $response->assertOk()->assertJsonPath('data.text.engine.filters.*.name', ['number', 'price', 'active', 'code']);
    }

    #[Test]
    public function it_describes_a_database_text_search_on_a_searchable_model(): void
    {
        $this->assertTextDetails(
            '{"fields":["title"],"modes":["words","phrase"],"relevance":false,"trashed":true,"max_length":255,"engine":null}',
            $this->getJson('/api/contains-vouchers'),
        );
    }

    private function assertTextDetails(string $expected, TestResponse $response): void
    {
        $response->assertOk();

        $this->assertSame($expected, json_encode(json_decode((string) $response->getContent(), flags: JSON_THROW_ON_ERROR)->data->text));
    }
}
