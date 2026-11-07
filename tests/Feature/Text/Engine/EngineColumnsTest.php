<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineColumnsTest extends EngineSearchTestCase
{
    #[Test]
    public function it_loads_the_columns_that_an_engine_page_renders(): void
    {
        DB::enableQueryLog();

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'selects' => [['field' => 'title']]]);

        $response->assertOk()->assertJsonPath('data', [['title' => 'Report inactive'], ['title' => 'Report blue'], ['title' => 'Report alpha'], ['title' => 'Report beta']]);
        $this->assertSame('select vouchers.title, vouchers.id, vouchers.code from vouchers where vouchers.code in (?, ?, ?, ?) and vouchers.deleted_at is null', $this->loggedQuery(1));
    }

    #[Test]
    public function it_loads_every_column_of_the_rows_of_an_engine_action(): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertJsonPath('data.impacted', 4);
        $this->assertSame('select * from vouchers where vouchers.code in (?, ?, ?, ?) and vouchers.deleted_at is null', $this->loggedQuery(1));
    }

    private function loggedQuery(int $index): string
    {
        return str_replace(['"', '`'], '', DB::getQueryLog()[$index]['query']);
    }
}
