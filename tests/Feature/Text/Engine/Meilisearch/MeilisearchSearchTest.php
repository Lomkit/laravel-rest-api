<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Meilisearch;

use Lomkit\Rest\Tests\Feature\Text\Engine\SearchesAnIndex;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('meilisearch')]
class MeilisearchSearchTest extends MeilisearchTestCase
{
    use SearchesAnIndex;

    #[Test]
    public function it_reads_integer_scout_keys_from_the_attribute_of_the_hits(): void
    {
        $alpha = $this->search('numbered-vouchers', ['text' => ['value' => 'alpha']]);
        $report = $this->search('numbered-vouchers', ['text' => ['value' => 'report'], 'sorts' => self::BY_PRICE]);

        $alpha->assertOk()->assertExactJson($this->page([1], 1));
        $report->assertOk()->assertExactJson($this->page([1, 3, 5], 4));
    }
}
