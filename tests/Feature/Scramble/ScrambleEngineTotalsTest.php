<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TotalledVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ScrambleEngineTotalsTest extends ScrambleTestCase
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
    public function it_documents_the_totals_of_the_engine_branch_and_of_the_page(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('totalled-vouchers', TotalledVoucherController::class));

        $schemas = $this->schemas();

        $this->assertSame(['$ref' => '#/components/schemas/TotalledVoucherEngineSearchTotals'], $schemas['TotalledVoucherEngineSearch']['properties']['totals']);
        $this->assertArrayNotHasKey('totals', $schemas['TotalledVoucherDatabaseSearch']['properties']);
        $this->assertSame([
            'type' => 'object',
            'description' => 'The totals of every row that the search engine matches, returned under totals at the root of the page: count always, the measures and the groups asked for. Send measures, groups or both.',
            'properties' => [
                'measures' => [
                    'type' => 'array',
                    'description' => 'Distinct measures',
                    'items' => [
                        'type' => 'object',
                        'description' => 'A measure over every matched row, returned in totals.all under <type>_<field>. sum ignores null values and is 0 without rows; avg, min and max ignore null values and are null without rows.',
                        'properties' => ['type' => ['type' => 'string', 'enum' => ['sum', 'avg', 'min', 'max']], 'field' => ['type' => 'string', 'enum' => ['price', 'number']]],
                        'required' => ['type', 'field'],
                        'additionalProperties' => false,
                    ],
                    'maxItems' => 20,
                ],
                'groups' => [
                    'type' => 'array',
                    'description' => 'Groups on distinct fields',
                    'items' => [
                        'type' => 'object',
                        'description' => 'Counts the matched rows per value of one field, independently of the other groups. The values come by count, descending, then by value; the rows outside them, other values and rows without a value, are counted in other.',
                        'properties' => ['field' => ['type' => 'string', 'enum' => ['team', 'active', 'number']], 'limit' => ['type' => 'integer', 'description' => 'The most values returned, 100 by default', 'minimum' => 1, 'maximum' => 100]],
                        'required' => ['field'],
                        'additionalProperties' => false,
                    ],
                    'maxItems' => 3,
                ],
            ],
            'additionalProperties' => false,
            'minProperties' => 1,
            'title' => 'TotalledVoucherEngineSearchTotals',
        ], $schemas['TotalledVoucherEngineSearchTotals']);
        $this->assertSame(['count', 'sum_price', 'avg_price', 'min_price', 'max_price', 'sum_number', 'avg_number', 'min_number', 'max_number'], array_keys($schemas['TotalledVoucherTotals']['properties']['all']['properties']));
        $this->assertSame(['team', 'active', 'number'], $schemas['TotalledVoucherTotalsGroup']['properties']['field']['enum']);
    }
}
