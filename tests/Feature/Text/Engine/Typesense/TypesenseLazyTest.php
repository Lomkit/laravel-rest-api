<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Typesense;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingTypesenseEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Typesense\Client;

#[Group('scout')]
#[Group('typesense')]
class TypesenseLazyTest extends TypesenseTestCase
{
    private const int EXTRA = 255;

    #[\Override]
    protected function seedLedger(): void
    {
        parent::seedLedger();

        Voucher::withoutSyncingToSearch(function (): void {
            foreach (range(7, 6 + self::EXTRA) as $id) {
                Voucher::query()->create(['id' => $id, 'code' => sprintf('W-%03d', $id), 'number' => $id, 'team' => 'red', 'title' => "Report extra {$id}", 'price' => 2.5, 'active' => true]);
            }
        });
        Voucher::query()
            ->where('id', '>', 6)
            ->get()
            ->searchable();
    }

    #[Test]
    public function it_reads_every_row_of_a_text_search_over_several_typesense_pages_once(): void
    {
        $engine = new RecordingTypesenseEngine(new Client(config('scout.typesense.client-settings')), 1000, config('scout.typesense'));
        $this->useEngine($engine);

        $rows = Rest::search(VoucherResource::class)
            ->as(null)
            ->payload(['text' => ['value' => 'report'], 'sorts' => [['field' => 'number', 'direction' => 'asc']]])
            ->lazy(chunk: 100);

        $this->assertSame([[250, 1], [250, 2]], $engine->pages);
        $this->assertSame([3, 1, 4, 5, ...range(7, 6 + self::EXTRA)], $rows->pluck('id')->all());
    }
}
