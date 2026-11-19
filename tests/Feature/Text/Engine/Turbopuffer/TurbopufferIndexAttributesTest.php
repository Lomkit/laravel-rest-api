<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Turbopuffer;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PerimeterPrefixedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PrefixedVoucherController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('turbopuffer')]
class TurbopufferIndexAttributesTest extends TurbopufferTestCase
{
    private const array RANKED = ['rank_by' => ['title', 'BM25', 'report']];

    private const array COUNT = ['aggregate_by' => ['count' => ['Count']]];

    private const array UNTRASHED = ['__soft_deleted', 'Eq', 0];

    #[\Override]
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::prefix('api')->group(function (): void {
            Rest::resource('prefixed-vouchers', PrefixedVoucherController::class)->withSoftDeletes();
            Rest::resource('perimeter-prefixed-vouchers', PerimeterPrefixedVoucherController::class)->withSoftDeletes();
        });
    }

    /** @param list<mixed> $filters */
    #[Test]
    #[DataProvider('filters')]
    public function it_sends_the_list_of_an_instruction_as_contains_any(string $resource, array $filters): void
    {
        $this->rows([], 0);

        $response = $this->search($resource, ['text' => ['value' => 'report'], 'instructions' => [['name' => 'name-prefixes']]]);

        $response->assertOk()->assertExactJson($this->page([], 0));
        $this->assertSame([
            [...self::RANKED, 'filters' => $filters, 'limit' => 50],
            [...self::COUNT, 'filters' => $filters],
        ], $this->queries());
    }

    /** @return array<string, array{string, list<mixed>}> */
    public static function filters(): array
    {
        return [
            'without perimeter' => ['prefixed-vouchers', ['And', [['name_prefixes', 'ContainsAny', ['re', 'rep']], self::UNTRASHED]]],
            'beside a perimeter' => ['perimeter-prefixed-vouchers', ['And', [['name_prefixes', 'ContainsAny', ['re', 'rep']], ['And', [self::UNTRASHED, ['team', 'Eq', 'red']]]]]],
        ];
    }
}
