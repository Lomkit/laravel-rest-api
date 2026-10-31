<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Typesense;

use Lomkit\Rest\Tests\Feature\Text\Engine\SearchesAnIndex;
use Lomkit\Rest\Tests\Fixtures\Ledger\NumberedVoucher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('typesense')]
class TypesenseSearchTest extends TypesenseTestCase
{
    use SearchesAnIndex;

    #[Test]
    public function it_reads_integer_scout_keys_from_the_string_ids_of_the_index(): void
    {
        $this->collections->drop($this->collection);
        NumberedVoucher::query()
            ->withTrashed()
            ->get()
            ->searchable();

        $alpha = $this->search('numbered-vouchers', ['text' => ['value' => 'alpha']]);
        $report = $this->search('numbered-vouchers', ['text' => ['value' => 'report'], 'sorts' => self::BY_PRICE]);

        $alpha->assertOk()->assertExactJson($this->page([1], 1));
        $report->assertOk()->assertExactJson($this->page([1, 3, 5], 4));
    }
}
