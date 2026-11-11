<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Fields\Stubs\UtcDateTime;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\UncastShowController;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Festival\Festival;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class DateTimeOffsetTest extends PackageTestCase
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

        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $this->travelTo('2026-10-01 09:00:00');
        DB::table('shows')->insert([
            self::show(1, '2026-10-11', '2026-10-12 05:00:00'),
            self::show(2, '2026-10-12', '2026-10-12 06:00:00'),
            self::show(3, '2026-10-13', '2026-10-12 09:00:00'),
        ]);
    }

    /**
     * @param  list<string>|string  $value
     * @param  list<int>  $expected
     */
    #[Test]
    #[DataProvider('offsetFilters')]
    public function it_filters_a_value_with_an_offset_at_the_same_instant(string $operator, array|string $value, array $expected): void
    {
        $this->assertSame($expected, $this->searchedKeys([['field' => 'starts_at', 'operator' => $operator, 'value' => $value]]));
    }

    /** @return iterable<string, array{string, list<string>|string, list<int>}> */
    public static function offsetFilters(): iterable
    {
        yield '>=' => ['>=', '2026-10-12T08:00:00+02:00', [2, 3]];
        yield '=' => ['=', '2026-10-12T08:00:00+02:00', [2]];
        yield 'between' => ['between', ['2026-10-12T07:30:00+02:00', '2026-10-12T08:30:00+02:00'], [2]];
        yield 'not between' => ['not between', ['2026-10-12T07:30:00+02:00', '2026-10-12T08:30:00+02:00'], [1, 3]];
    }

    #[Test]
    public function it_converts_a_utc_value_to_the_wall_time_of_the_application_timezone(): void
    {
        Config::set('app.timezone', 'Europe/Paris');

        $this->assertSame([2], $this->searchedKeys([['field' => 'starts_at', 'value' => '2026-10-12T04:00:00Z']]));
    }

    #[Test]
    public function it_reads_a_value_without_an_offset_in_the_application_timezone(): void
    {
        Rest::resource('uncast-shows', UncastShowController::class)->register();

        $this->assertSame([2, 3], $this->searchedKeys([['field' => 'starts_at', 'operator' => '>=', 'value' => '2026-10-12 06:00:00']]));
        $this->postJson('/uncast-shows/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'attributes' => ['starts_at' => '2026-10-12 08:00:00']],
        ]])->assertOk();
        $this->assertSame('2026-10-12 08:00:00', self::stored('shows', 1, 'starts_at'));
    }

    #[Test]
    public function it_keeps_the_calendar_day_of_a_date_value_with_an_offset(): void
    {
        $this->assertSame([2], $this->searchedKeys([['field' => 'starts_on', 'value' => '2026-10-12T23:30:00-05:00']]));
    }

    #[Test]
    public function it_stores_the_instant_of_a_written_value_with_an_offset_in_the_application_timezone(): void
    {
        $response = $this->postJson('/api/shows/mutate', [
            'mutate' => [
                ['operation' => 'create', 'attributes' => ['title' => 'Late show', 'starts_on' => '2026-10-12', 'starts_at' => '2026-10-12T08:00:00+02:00', 'status' => 'planned']],
                ['operation' => 'update', 'key' => 1, 'attributes' => ['starts_at' => '2026-10-12T23:30:00-05:00']],
            ],
            'response' => ['selects' => [['field' => 'id'], ['field' => 'starts_at']]],
        ]);

        $response->assertOk()->assertJsonPath('records', [
            ['id' => 4, 'starts_at' => '2026-10-12T06:00:00.000000Z'],
            ['id' => 1, 'starts_at' => '2026-10-13T04:30:00.000000Z'],
        ]);
        $this->assertSame('2026-10-12 06:00:00', self::stored('shows', 4, 'starts_at'));
        $this->assertSame('2026-10-13 04:30:00', self::stored('shows', 1, 'starts_at'));
    }

    #[Test]
    public function it_stores_a_value_with_an_offset_in_the_storage_format_on_a_column_without_cast(): void
    {
        Rest::resource('uncast-shows', UncastShowController::class)->register();

        $this->postJson('/uncast-shows/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'attributes' => ['starts_at' => '2026-10-12T08:00:00.250000+02:00']],
            ['operation' => 'update', 'key' => 2, 'attributes' => ['starts_at' => '2026-10-12T08:00:00+02:00']],
        ]])->assertOk();

        $this->assertSame('2026-10-12 06:00:00.250000', self::stored('shows', 1, 'starts_at'));
        $this->assertSame('2026-10-12 06:00:00', self::stored('shows', 2, 'starts_at'));
    }

    #[Test]
    public function it_stores_a_pivot_value_with_an_offset_in_the_application_timezone(): void
    {
        Rest::resource('uncast-shows', UncastShowController::class)->register();
        DB::table('artists')->insert([['id' => 1, 'name' => 'Nina']]);

        $this->postJson('/uncast-shows/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'relations' => ['artists' => [
                ['operation' => 'attach', 'key' => [1], 'pivot' => ['headliner' => true, 'confirmed_at' => '2026-10-12T08:00:00+02:00']],
            ]]],
        ]])->assertOk();

        $this->assertSame('2026-10-12 06:00:00', DB::table('artist_show')->where('artist_id', 1)->value('confirmed_at'));
    }

    #[Test]
    public function it_hands_an_action_input_with_an_offset_to_the_action_as_the_converted_instant(): void
    {
        $this->postJson('/api/shows/actions/reschedule-shows', [
            'search' => ['filters' => [['field' => 'id', 'value' => 1]]],
            'fields' => [['name' => 'at', 'value' => '2026-10-12T08:00:00+02:00']],
        ])->assertOk();

        $this->assertSame('2026-10-12 06:00:00', self::stored('shows', 1, 'starts_at'));
    }

    #[Test]
    public function it_runs_the_normalize_override_of_a_subclass_on_filters_and_writes(): void
    {
        Config::set('app.timezone', 'Europe/Paris');
        $field = UtcDateTime::make('starts_at')->boundTo(new Show);
        $query = Show::query();
        $show = new Show;

        $field->applyFilter($query, '>=', '2026-10-12T08:00:00+02:00');
        $field->fill($show, '2026-10-12T08:00:00+02:00');

        $this->assertSame(['2026-10-12 06:00:00'], $query->getBindings());
        $this->assertSame('2026-10-12 06:00:00', $show->getAttributes()['starts_at']);
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @return list<int>
     */
    private function searchedKeys(array $filters): array
    {
        $response = $this->postJson('/api/shows/search', ['search' => ['filters' => $filters, 'selects' => [['field' => 'id']], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk();

        return array_column((array) $response->json('data'), 'id');
    }

    private static function stored(string $table, int $id, string $column): mixed
    {
        return DB::table($table)->where('id', $id)->value($column);
    }

    /** @return array<string, mixed> */
    private static function show(int $id, string $startsOn, string $startsAt): array
    {
        return [
            'id' => $id,
            'title' => 'Show '.$id,
            'starts_on' => $startsOn,
            'starts_at' => $startsAt,
            'status' => 'planned',
            'created_at' => '2026-10-01 09:00:00',
            'updated_at' => '2026-10-01 09:00:00',
        ];
    }
}
