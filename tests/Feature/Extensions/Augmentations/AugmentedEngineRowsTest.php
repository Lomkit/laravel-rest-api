<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Augmentations;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Extensions\Contexts\AugmentationContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AugmentationLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstAugmentation;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[WithConfig('rest.extensions', ['first' => FirstAugmentation::class])]
class AugmentedEngineRowsTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        AugmentationLog::reset();
        FirstAugmentation::$augment = static function (AugmentationContext $context): void {
            $context->select('marker', static function (Builder $query): void {
                $query->selectRaw("'seen'");
            });
        };
    }

    #[\Override]
    protected function tearDown(): void
    {
        AugmentationLog::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_augments_the_rows_read_again_for_an_engine_page(): void
    {
        DB::enableQueryLog();

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'selects' => [['field' => 'title']]]);

        $response->assertOk()->assertJsonPath('data', [['title' => 'Report inactive'], ['title' => 'Report blue'], ['title' => 'Report alpha'], ['title' => 'Report beta']]);
        $this->assertSame('select vouchers.title, vouchers.id, vouchers.code, (select \'seen\') as first__marker from vouchers where vouchers.code in (?, ?, ?, ?) and vouchers.deleted_at is null', $this->loggedQuery(1));
    }

    #[Test]
    public function it_does_not_augment_the_rows_of_an_action_on_engine_results(): void
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
