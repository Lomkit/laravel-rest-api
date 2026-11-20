<?php

namespace Lomkit\Rest\Tests\Feature\Search\Totals;

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Extensions\Contexts\LimitsContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstNarrowing;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MaskingFields;
use Lomkit\Rest\Tests\Feature\Search\Totals\Stubs\CappedShowController;
use Lomkit\Rest\Tests\Feature\Search\Totals\Stubs\PagedShowController;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', [MaskingFields::class, FirstNarrowing::class])]
class SearchTotalsDetailsTest extends SearchTotalsTestCase
{
    use ResetsExtensionStubs;

    private const array NONE = [
        'id' => [false, []], 'title' => [false, []], 'summary' => [false, []], 'price' => [false, []], 'seats' => [false, []],
        'featured' => [false, []], 'starts_on' => [false, []], 'starts_at' => [false, []], 'status' => [false, []], 'level' => [false, []],
        'settings' => [false, []], 'secret' => [false, []], 'press_note' => [false, []], 'headline' => [false, []], 'updated_at' => [false, []],
    ];

    private const array MEASURES = ['sum', 'avg', 'min', 'max'];

    #[\Override]
    protected function tearDown(): void
    {
        FirstNarrowing::$narrow = null;

        parent::tearDown();
    }

    #[Test]
    public function it_describes_the_groups_and_measures_of_each_field(): void
    {
        $this->assertSame([
            ...self::NONE,
            'title' => [true, []],
            'summary' => [true, []],
            'price' => [false, self::MEASURES],
            'seats' => [true, self::MEASURES],
            'featured' => [true, []],
            'status' => [true, []],
            'level' => [true, []],
        ], $this->totals($this->getJson('/api/shows?with=fields.capabilities')));
    }

    #[Test]
    public function it_describes_the_totals_that_the_fields_narrowed_for_the_user_keep(): void
    {
        MaskingFields::$narrow = static function (FieldsContext $context): void {
            if ($context->resource instanceof ShowResource) {
                $context->hide('summary');
                $context->unfilterable('status');
                $context->unfilterable('seats');
            }
        };

        $this->assertSame([
            ...array_diff_key(self::NONE, ['summary' => true]),
            'title' => [true, []],
            'price' => [false, self::MEASURES],
            'featured' => [true, []],
            'level' => [true, []],
        ], $this->totals($this->getJson('/api/shows?with=fields.capabilities')));
    }

    #[Test]
    public function it_describes_no_totals_when_the_resource_refuses_them_for_the_user(): void
    {
        Route::prefix('api')->group(static function (): void {
            Rest::resource('capped-shows', CappedShowController::class)->register();
            Rest::resource('paged-shows', PagedShowController::class)->register();
        });

        $capped = $this->totals($this->getJson('/api/capped-shows?with=fields.capabilities'));
        $paged = $this->totals($this->getJson('/api/paged-shows?with=fields.capabilities'));
        FirstNarrowing::$narrow = static fn (LimitsContext $context): mixed => $context->maxTotal(100);
        $narrowed = $this->totals($this->getJson('/api/shows?with=fields.capabilities'));

        $this->assertSame(self::NONE, $capped);
        $this->assertSame(self::NONE, $paged);
        $this->assertSame(self::NONE, $narrowed);
    }

    /** @return array<string, array{bool, list<string>}> */
    private function totals(TestResponse $response): array
    {
        $response->assertOk();
        $fields = [];

        foreach ($response->json('data.fields') as $field) {
            $fields[$field['name']] = [$field['group'], $field['measures']];
        }

        return $fields;
    }
}
