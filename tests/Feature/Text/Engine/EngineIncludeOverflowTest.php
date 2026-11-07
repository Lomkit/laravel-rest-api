<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherLine;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineIncludeOverflowTest extends EngineSearchTestCase
{
    #[Test]
    public function it_lists_the_includes_with_more_rows_on_the_rows_of_an_engine_page(): void
    {
        foreach (range(6, 15) as $id) {
            VoucherLine::query()->create(['id' => $id, 'voucher_id' => 1, 'label' => 'l1'.$id]);
        }

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'selects' => [['field' => 'id']], 'includes' => [['relation' => 'lines', 'selects' => [['field' => 'id']]]]]);

        $response->assertOk();
        $this->assertSame(
            [[5, [4], []], [4, [5], []], [1, range(15, 6), ['lines']], [3, [3], []]],
            array_map(fn (array $voucher): array => [$voucher['id'], array_column($voucher['lines'], 'id'), $voucher['@more']], $response->json('data')),
        );
    }
}
