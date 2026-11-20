<?php

namespace Lomkit\Rest\Tests\Feature\Search\Totals;

use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Exceptions\SearchRunException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;
use PHPUnit\Framework\Attributes\Test;

class SearchTotalsRunTest extends SearchTotalsTestCase
{
    private const array TOTALS = [
        'measures' => [['type' => 'sum', 'field' => 'seats'], ['type' => 'avg', 'field' => 'seats'], ['type' => 'sum', 'field' => 'price'], ['type' => 'min', 'field' => 'price']],
        'groups' => [['field' => 'level', 'limit' => 2]],
    ];

    #[Test]
    public function it_renders_the_totals_at_the_root_with_their_php_types(): void
    {
        $body = Rest::search(ShowResource::class)
            ->as(new User(['id' => 1, 'name' => 'Ada']))
            ->payload(['selects' => [['field' => 'id']], 'totals' => self::TOTALS])
            ->render();

        $this->assertSame(5, $body['total']);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 5, 'sum_seats' => 750, 'avg_seats' => 150.0, 'sum_price' => 43.0, 'min_price' => 5.0],
            'groups' => [['field' => 'level', 'buckets' => [['value' => 1, 'count' => 3], ['value' => 2, 'count' => 1]], 'other' => ['count' => 1], 'truncated' => true]],
        ], $body['totals']);
    }

    #[Test]
    public function it_refuses_page_when_the_payload_asks_for_totals(): void
    {
        $this->expectExceptionObject(SearchRunException::totalsOnPage(ShowResource::class));

        Rest::search(ShowResource::class)
            ->as(new User(['id' => 1, 'name' => 'Ada']))
            ->payload(['totals' => self::TOTALS])
            ->page();
    }

    #[Test]
    public function it_refuses_totals_in_lazy(): void
    {
        try {
            Rest::search(ShowResource::class)
                ->as(new User(['id' => 1, 'name' => 'Ada']))
                ->payload(['totals' => self::TOTALS])
                ->lazy()
                ->all();
            $this->fail('lazy() accepted totals.');
        } catch (ValidationException $exception) {
            $this->assertSame(['search.totals' => ['The search.totals field is prohibited.']], $exception->errors());
        }
    }
}
