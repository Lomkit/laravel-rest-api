<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineGatesTest extends EngineSearchTestCase
{
    use CapturesQueries;

    private const array GATES = ['view', 'update', 'delete', 'restore', 'forceDelete'];

    private const array SELECTS = [['field' => 'id']];

    private const string MEMBERSHIP = 'select vouchers.id from vouchers where (team = ?)';

    /**
     * @param  array<string, mixed>  $search
     * @param  list<int>  $ids
     * @param  array<string, list<bool>>  $allowed
     * @param  list<array{string, list<mixed>}>  $membership
     */
    #[Test]
    #[DataProvider('pages')]
    public function it_answers_false_to_the_write_gates_of_a_page_row_outside_the_perimeter(string $driver, bool $softDelete, array $search, array $ids, array $allowed, array $membership): void
    {
        config(['scout.driver' => $driver, 'scout.soft_delete' => $softDelete]);
        $this->captureQueries();

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'gates' => self::GATES, 'selects' => self::SELECTS, ...$search]);

        $response->assertOk()->assertJsonPath('data.*.id', $ids);

        foreach ($allowed as $gate => $answers) {
            $response->assertJsonPath("data.*.gates.authorized_to_{$gate}.allowed", $answers);
        }

        $this->assertSame([
            'authorized_to_view' => ['allowed' => true, 'message' => null],
            'authorized_to_update' => ['allowed' => false, 'message' => null],
            'authorized_to_delete' => ['allowed' => false, 'message' => null],
            'authorized_to_restore' => ['allowed' => false, 'message' => null],
            'authorized_to_force_delete' => ['allowed' => false, 'message' => null],
        ], $response->json('data.'.array_search(4, $ids, true).'.gates'));
        $this->assertSame($membership, array_values(array_filter(
            $this->readsOf('vouchers'),
            static fn (array $read): bool => str_starts_with($read[0], self::MEMBERSHIP),
        )));
    }

    /** @return iterable<string, array{string, bool, array<string, mixed>, list<int>, array<string, list<bool>>, list<array{string, list<mixed>}>}> */
    public static function pages(): iterable
    {
        foreach (self::drivers() as $engine => [$driver]) {
            yield "the rows of the index on {$engine}" => [$driver, false, [], [5, 4, 1, 3], [
                'view' => [true, true, true, true],
                'update' => [true, false, true, true],
                'delete' => [true, false, true, true],
                'restore' => [true, false, true, true],
                'force_delete' => [true, false, true, true],
            ], [
                [self::MEMBERSHIP.' and vouchers.id in (5, 4, 1, 3) and vouchers.deleted_at is null', ['red']],
            ]];
            yield "the rows of the index with the trashed rows on {$engine}" => [$driver, true, ['trashed' => 'with'], [6, 5, 4, 1, 3], [
                'view' => [true, true, true, true, true],
                'update' => [false, true, false, true, true],
                'delete' => [false, true, false, true, true],
                'restore' => [true, true, false, true, true],
                'force_delete' => [true, true, false, true, true],
            ], [
                [self::MEMBERSHIP.' and vouchers.id in (6, 5, 4, 1, 3) and vouchers.deleted_at is null', ['red']],
                [self::MEMBERSHIP.' and vouchers.id in (6, 5, 4, 1, 3)', ['red']],
            ]];
        }
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_reads_no_membership_for_the_view_gate_and_the_includes_of_the_page(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $this->captureQueries();

        $response = $this->search('vouchers', [
            'text' => ['value' => 'report'],
            'gates' => ['view'],
            'selects' => self::SELECTS,
            'includes' => [['relation' => 'lines', 'gates' => ['update', 'delete'], 'selects' => self::SELECTS]],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [5, 4, 1, 3])
            ->assertJsonPath('data.*.gates.authorized_to_view.allowed', [true, true, true, true])
            ->assertJsonPath('data.*.lines.*.gates.authorized_to_update.allowed', [true, true, true, true, true])
            ->assertJsonPath('data.*.lines.*.gates.authorized_to_delete.allowed', [true, true, true, true, true]);
        $this->assertSame([], array_values(array_filter(
            $this->readsOf('vouchers'),
            static fn (array $read): bool => str_contains($read[0], '(team = ?)'),
        )));
    }
}
