<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Grouped\GroupedLineController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Grouped\GroupedVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class GroupedScopesTest extends PackageTestCase
{
    use CapturesQueries;

    private const array WIDE = ['scopes' => [['name' => 'wide']]];

    private const array TITLED_OR_PRICEY = ['instructions' => [['name' => 'titled-or-pricey', 'fields' => [['name' => 'title', 'value' => 'Report alpha']]]]];

    private const array SELECTS = ['selects' => [['field' => 'id']]];

    private ReceivedFields $received;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Ledger];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Ledger::seed();
        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        Rest::resource('grouped-vouchers', GroupedVoucherController::class)->withSoftDeletes()->register();
        Rest::resource('grouped-lines', GroupedLineController::class)->register();
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('searches')]
    public function it_keeps_the_rows_of_a_scope_or_an_instruction_inside_the_perimeter(array $search, array $ids): void
    {
        $response = $this->postJson('/grouped-vouchers/search', ['search' => [...self::SELECTS, ...$search]]);

        $response->assertOk();
        $this->assertSame($ids, $response->json('data.*.id'));
    }

    /** @return array<string, array{array<string, mixed>, list<int>}> */
    public static function searches(): array
    {
        return [
            'a scope that starts with or' => [self::WIDE, [5, 3, 2, 1]],
            'a scope that starts with or with the trashed rows' => [[...self::WIDE, 'trashed' => 'with'], [6, 5, 3, 2, 1]],
            'an instruction that holds an or' => [self::TITLED_OR_PRICEY, [5, 2, 1]],
        ];
    }

    #[Test]
    public function it_groups_a_scope_that_starts_with_or_after_the_perimeter(): void
    {
        $this->captureQueries();

        $this->postJson('/grouped-vouchers/search', ['search' => [...self::SELECTS, ...self::WIDE]])->assertOk();

        $this->assertSame(
            [['select vouchers.id from vouchers where (team = ?) and (vouchers.id > ?) and vouchers.deleted_at is null order by vouchers.id desc limit 50 offset 0', ['red', 0]]],
            $this->readsOf('vouchers'),
        );
    }

    #[Test]
    public function it_groups_an_instruction_that_holds_an_or_after_the_perimeter(): void
    {
        $this->captureQueries();

        $this->postJson('/grouped-vouchers/search', ['search' => [...self::SELECTS, ...self::TITLED_OR_PRICEY]])->assertOk();

        $this->assertSame(
            [['select vouchers.id from vouchers where (team = ?) and (title = ? or price > ?) and vouchers.deleted_at is null order by vouchers.id desc limit 50 offset 0', ['red', 'Report alpha', 10]]],
            $this->readsOf('vouchers'),
        );
    }

    #[Test]
    public function it_keeps_an_included_relation_inside_its_perimeter_under_an_instruction_that_holds_an_or(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/grouped-lines/search', ['search' => [
            ...self::SELECTS,
            'includes' => [['relation' => 'voucher', ...self::SELECTS, ...self::TITLED_OR_PRICEY]],
        ]]);

        $response->assertOk();
        $this->assertSame(
            [[5, null], [4, 5], [3, null], [2, 1], [1, 1]],
            array_map(fn (array $line): array => [$line['id'], $line['voucher']['id'] ?? null], $response->json('data')),
        );
        $this->assertSame(
            [['select vouchers.id from vouchers where vouchers.id in (1, 3, 4, 5) and (team = ?) and (title = ? or price > ?) and vouchers.deleted_at is null', ['red', 'Report alpha', 10]]],
            $this->readsOf('vouchers'),
        );
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('counts')]
    public function it_counts_only_the_rows_of_the_perimeter_under_a_scope_or_an_instruction(array $search, int $count): void
    {
        $response = $this->postJson('/grouped-vouchers/metrics/row-count', ['search' => $search]);

        $response->assertOk()->assertExactJson(['data' => ['type' => 'count', 'value' => $count]]);
    }

    /** @return array<string, array{array<string, mixed>, int}> */
    public static function counts(): array
    {
        return [
            'a scope that starts with or' => [self::WIDE, 4],
            'an instruction that holds an or' => [self::TITLED_OR_PRICEY, 3],
        ];
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('actions')]
    public function it_acts_only_on_the_rows_of_the_perimeter_under_a_scope_or_an_instruction(array $search, array $ids): void
    {
        $response = $this->postJson('/grouped-vouchers/actions/deactivate-vouchers', ['search' => $search]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => count($ids), 'result' => null]]);
        $this->assertSame([['handle', ['ids' => $ids]]], $this->received->calls());
        $this->assertTrue(Voucher::query()->findOrFail(4)->active);
    }

    /** @return array<string, array{array<string, mixed>, list<int>}> */
    public static function actions(): array
    {
        return [
            'a scope that starts with or' => [self::WIDE, [1, 2, 3, 5]],
            'an instruction that holds an or' => [self::TITLED_OR_PRICEY, [1, 2, 5]],
        ];
    }

    #[Test]
    public function it_keeps_the_order_that_a_scope_adds(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/grouped-vouchers/search', ['search' => [
            ...self::SELECTS,
            'scopes' => [['name' => 'cheapFirst']],
            'sorts' => [['field' => 'active']],
        ]]);

        $response->assertOk();
        $this->assertSame([3, 1], $response->json('data.*.id'));
        $this->assertSame(
            [['select vouchers.id from vouchers where (team = ?) and (price < ?) and vouchers.deleted_at is null order by vouchers.active asc, price desc, vouchers.id desc limit 50 offset 0', ['red', 10]]],
            $this->readsOf('vouchers'),
        );
    }
}
