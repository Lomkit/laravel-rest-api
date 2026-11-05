<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\LinedVoucherController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class DatabaseEnginePageTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config(['scout.driver' => 'database']);
        Route::prefix('api')->group(fn () => Rest::resource('lined-vouchers', LinedVoucherController::class)->register());
        DB::enableQueryLog();
    }

    #[Test]
    public function it_reads_only_the_scout_key_of_the_rows_of_a_page(): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'selects' => [['field' => 'title']]]);

        $response->assertOk()->assertJsonPath('data', [['title' => 'Report inactive'], ['title' => 'Report alpha'], ['title' => 'Report beta']]);
        $this->assertStringStartsWith('select count(*) as aggregate from vouchers where ', $this->loggedQuery(0));
        $this->assertStringStartsWith('select vouchers.code from vouchers where ', $this->loggedQuery(1));
        $this->assertStringEndsWith(' order by vouchers.code desc limit 50 offset 0', $this->loggedQuery(1));
    }

    #[Test]
    public function it_reads_only_the_scout_key_of_the_rows_of_an_action(): void
    {
        $response = $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertJsonPath('data.impacted', 3);
        $this->assertStringStartsWith('select vouchers.code from vouchers where ', $this->loggedQuery(1));
        $this->assertStringEndsWith(' order by vouchers.code desc limit 100 offset 0', $this->loggedQuery(1));
    }

    #[Test]
    public function it_skips_the_eager_loads_of_the_model_on_the_rows_of_a_page(): void
    {
        $response = $this->search('lined-vouchers', ['text' => ['value' => 'report'], 'selects' => [['field' => 'title']]]);

        $response->assertOk()->assertJsonPath('data', [['title' => 'Report inactive'], ['title' => 'Report alpha'], ['title' => 'Report beta']]);
        $this->assertStringStartsWith('select vouchers.code from vouchers where ', $this->loggedQuery(1));
        $this->assertStringStartsWith('select vouchers.title, vouchers.id, vouchers.code from vouchers where ', $this->loggedQuery(2));
    }

    #[Test]
    public function it_skips_the_eager_loads_of_the_model_on_the_rows_of_an_action(): void
    {
        $response = $this->postJson('/api/lined-vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertJsonPath('data.impacted', 3);
        $this->assertStringStartsWith('select vouchers.code from vouchers where ', $this->loggedQuery(1));
        $this->assertStringStartsWith('select * from vouchers where ', $this->loggedQuery(2));
    }

    private function loggedQuery(int $index): string
    {
        return str_replace(['"', '`'], '', DB::getQueryLog()[$index]['query']);
    }
}
