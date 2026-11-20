<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Festival\Festival;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TermlessMetricTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Accounts, new Festival];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-01 09:00:00');
        DB::table('stages')->insert([['code' => 'main', 'name' => 'Main stage']]);
        DB::table('shows')->insert([
            ['stage_code' => 'main', 'title' => 'Opening night', 'seats' => 300, 'level' => 1, 'starts_on' => '2026-07-01', 'starts_at' => '2026-02-20 20:00:00', 'status' => 'on_sale'],
            ['stage_code' => 'main', 'title' => 'Closing night', 'seats' => 100, 'level' => 2, 'starts_on' => '2026-07-03', 'starts_at' => '2026-01-21 20:00:00', 'status' => 'planned'],
        ]);
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
    }

    /**
     * @param  array<string, mixed>  $full
     * @param  array<string, mixed>  $empty
     */
    #[Test]
    #[DataProvider('metrics')]
    public function it_computes_an_empty_metric_for_a_text_without_terms(string $metric, array $full, array $empty): void
    {
        $absent = $this->postJson("/api/shows/metrics/{$metric}", ['search' => []]);
        $termless = $this->postJson("/api/shows/metrics/{$metric}", ['search' => ['text' => ['value' => '*']]]);

        $absent->assertOk()->assertExactJson($full);
        $termless->assertOk()->assertExactJson($empty);
    }

    /** @return array<string, array{string, array<string, mixed>, array<string, mixed>}> */
    public static function metrics(): array
    {
        $months = fn (int ...$values): array => array_map(fn (string $label, int $value): array => ['label' => $label, 'value' => $value], ['2026-01', '2026-02', '2026-03'], $values);

        return [
            'a trend' => [
                'shows-per-month',
                ['data' => ['type' => 'trend', 'series' => $months(1, 1, 0), 'splits' => [['key' => 'on_sale', 'label' => 'OnSale', 'series' => $months(0, 1, 0)]], 'other' => ['label' => 'Other', 'series' => $months(1, 0, 0)]]],
                ['data' => ['type' => 'trend', 'series' => $months(0, 0, 0), 'splits' => [], 'other' => null]],
            ],
            'a partition' => [
                'shows-by-status',
                ['data' => ['type' => 'partition', 'parts' => [['key' => 'on_sale', 'label' => 'OnSale', 'value' => 1], ['key' => 'planned', 'label' => 'Planned', 'value' => 1]], 'other' => null]],
                ['data' => ['type' => 'partition', 'parts' => [], 'other' => null]],
            ],
        ];
    }
}
