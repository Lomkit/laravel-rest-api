<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Dedoc\Scramble\ScrambleServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ScrambleEngineTextTest extends ScrambleTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Ledger];
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [ScrambleServiceProvider::class, ScoutServiceProvider::class, RestServiceProvider::class];
    }

    #[Test]
    public function it_documents_the_words_searched_in_the_index_of_the_resource(): void
    {
        $search = $this->search('/vouchers/search');

        $this->assertSame([
            'type' => 'object',
            'description' => 'Words to search in the search index of the resource; the index decides which attributes they match',
            'properties' => [
                'value' => ['type' => ['string', 'null'], 'maxLength' => 255],
                'mode' => ['type' => 'string', 'enum' => ['words']],
            ],
            'not' => ['required' => ['trashed']],
        ], $search['text']);
    }

    #[Test]
    public function it_documents_the_database_filters_and_sorts_of_a_resource_searched_on_an_engine(): void
    {
        $search = $this->search('/vouchers/search');

        $this->assertSame(['id', 'code', 'number', 'team', 'title', 'price', 'active', 'lines.id', 'lines.voucher_id', 'lines.label'], array_keys((array) $this->schemas()['VoucherFieldFilter']['discriminator']['mapping']));
        $this->assertSame(['$ref' => '#/components/schemas/VoucherFilter'], $search['filters']['items']);
        $this->assertSame(['id', 'code', 'number', 'team', 'title', 'price', 'active', 'lines_count', 'lines_min_id', 'lines_min_voucher_id', 'lines_min_label', 'lines_max_id', 'lines_max_voucher_id', 'lines_max_label', 'lines_avg_voucher_id', 'lines_sum_voucher_id', 'lines_exists', '@relevance'], $search['sorts']['items']['properties']['field']['enum']);
    }

    /** @param array<string, mixed> $trashed */
    #[Test]
    #[DataProvider('trashed')]
    public function it_documents_whether_the_trashed_models_combine_with_a_text_search(bool $softDelete, array $trashed): void
    {
        config(['scout.soft_delete' => $softDelete]);

        $this->assertSame($trashed, $this->search('/vouchers/search')['trashed']);
    }

    /** @return array<string, array{bool, array<string, mixed>}> */
    public static function trashed(): array
    {
        return [
            'an index that keeps the trashed models' => [true, ['type' => 'string', 'enum' => ['with', 'only']]],
            'an index that drops the trashed models' => [false, [
                'type' => 'string',
                'description' => 'Cannot be combined with a text search: the search index of the resource does not keep the trashed models',
                'enum' => ['with', 'only'],
            ]],
        ];
    }
}
