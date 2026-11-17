<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Meilisearch;

use Lomkit\Rest\Tests\Feature\Text\Engine\ListsAnIndex;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('meilisearch')]
class MeilisearchListTest extends MeilisearchTestCase
{
    use ListsAnIndex;

    #[Test]
    public function it_keeps_the_exact_total_of_a_list_beyond_the_max_total_hits_of_the_index(): void
    {
        $this->indexes->configure($this->index, ['pagination' => ['maxTotalHits' => 3]]);

        $response = $this->search('listed-vouchers', ['limit' => 2]);

        $response->assertOk()->assertExactJson($this->page([5, 4], 5, 2));
    }
}
