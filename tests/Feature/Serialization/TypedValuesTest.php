<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Http\Serialization\RowSerializer;
use Lomkit\Rest\Search\Plan\QueryPlan;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\CastingShowController;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\ResolvedShowController;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\SerializedShowController;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\UncastShow;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\UncastShowController;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\UncastShowResource;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Festival\Festival;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class TypedValuesTest extends PackageTestCase
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
        DB::table('stages')->insert([['code' => 'main', 'name' => 'Main stage']]);
        DB::table('shows')->insert([
            $this->show(1, ['stage_code' => 'main', 'price' => '25000.50', 'seats' => 300, 'settings' => '{"lights":"warm"}']),
            $this->show(2, ['stage_code' => 'main', 'price' => null, 'seats' => 0, 'settings' => '[]']),
            $this->show(3, ['price' => '7', 'seats' => 12, 'settings' => '["warm","cold"]']),
            $this->show(4, ['price' => '0.10', 'seats' => 1, 'settings' => null]),
        ]);
    }

    #[Test]
    public function it_returns_a_decimal_cast_as_a_number_and_keeps_integers_and_nulls(): void
    {
        $response = $this->search('/api/shows/search', ['selects' => [['field' => 'price'], ['field' => 'seats']], 'sorts' => [['field' => 'id']]]);

        $this->assertSame('[{"price":25000.5,"seats":300},{"price":null,"seats":0},{"price":7,"seats":12},{"price":0.1,"seats":1}]', $this->data($response));
    }

    #[Test]
    public function it_returns_an_empty_json_value_as_an_object_and_keeps_objects_and_lists(): void
    {
        $response = $this->search('/api/shows/search', ['selects' => [['field' => 'id'], ['field' => 'settings']], 'sorts' => [['field' => 'id']]]);

        $this->assertSame('[{"id":1,"settings":{"lights":"warm"}},{"id":2,"settings":{}},{"id":3,"settings":["warm","cold"]},{"id":4,"settings":null}]', $this->data($response));
    }

    #[Test]
    public function it_returns_the_typed_values_of_an_included_row(): void
    {
        $response = $this->search('/api/stages/search', ['includes' => [[
            'relation' => 'shows',
            'selects' => [['field' => 'id'], ['field' => 'price'], ['field' => 'settings']],
            'sorts' => [['field' => 'id']],
        ]]]);

        $this->assertSame('[{"code":"main","name":"Main stage","shows":[{"id":1,"price":25000.5,"settings":{"lights":"warm"}},{"id":2,"price":null,"settings":{}}],"@more":[]}]', $this->data($response));
    }

    #[Test]
    public function it_returns_the_typed_values_of_a_mutation_record(): void
    {
        $response = $this->postJson('/api/shows/mutate', [
            'mutate' => [['operation' => 'update', 'key' => 3, 'attributes' => ['price' => 12.5, 'settings' => []]]],
            'response' => ['selects' => [['field' => 'price'], ['field' => 'seats'], ['field' => 'settings']]],
        ]);

        $response->assertOk();
        $this->assertSame('{"operations":[{"operation":"update","key":3}],"records":[{"price":12.5,"seats":12,"settings":{}}]}', $response->getContent());
    }

    #[Test]
    public function it_returns_the_value_of_a_resolved_field_in_its_type(): void
    {
        Rest::resource('resolved-shows', ResolvedShowController::class)->register();

        $response = $this->search('/resolved-shows/search', ['selects' => [['field' => 'id'], ['field' => 'total'], ['field' => 'extras']], 'sorts' => [['field' => 'id']]]);

        $this->assertSame('[{"id":1,"total":30,"extras":{}},{"id":2,"total":30,"extras":{}},{"id":3,"total":30,"extras":{}},{"id":4,"total":30,"extras":{}}]', $this->data($response));
    }

    #[Test]
    public function it_returns_a_boolean_and_a_date_time_without_cast_in_their_type(): void
    {
        Rest::resource('uncast-shows', UncastShowController::class)->register();
        DB::table('shows')->where('id', 1)->update(['featured' => true, 'starts_at' => '2026-02-10 09:30:00']);
        $search = ['selects' => [['field' => 'id'], ['field' => 'featured'], ['field' => 'starts_at'], ['field' => 'updated_at']], 'filters' => [['field' => 'id', 'operator' => 'in', 'value' => [1, 2]]], 'sorts' => [['field' => 'id']]];
        $rows = '[{"id":1,"featured":true,"starts_at":"2026-02-10T09:30:00.000000Z","updated_at":"2026-01-01T00:00:00.000000Z"},{"id":2,"featured":false,"starts_at":null,"updated_at":"2026-01-01T00:00:00.000000Z"}]';

        $this->assertSame($rows, $this->data($this->search('/uncast-shows/search', $search)));
        $this->assertSame($rows, $this->data($this->search('/api/shows/search', $search)));
    }

    #[Test]
    public function it_formats_a_date_time_without_cast_with_the_date_serialization_of_the_model(): void
    {
        Rest::resource('serialized-shows', SerializedShowController::class)->register();
        DB::table('shows')->where('id', 1)->update(['starts_at' => '2026-02-10 09:30:00']);

        $response = $this->search('/serialized-shows/search', ['selects' => [['field' => 'id'], ['field' => 'starts_at'], ['field' => 'updated_at']], 'filters' => [['field' => 'id', 'operator' => '=', 'value' => 1]]]);

        $this->assertSame('[{"id":1,"starts_at":"2026-02-10 09:30","updated_at":"2026-01-01 00:00"}]', $this->data($response));
    }

    #[Test]
    public function it_returns_the_value_of_an_accessor_over_the_type_of_its_field(): void
    {
        Rest::resource('serialized-shows', SerializedShowController::class)->register();
        DB::table('shows')->where('id', 1)->update(['featured' => true]);

        $response = $this->search('/serialized-shows/search', ['selects' => [['field' => 'id'], ['field' => 'featured']], 'filters' => [['field' => 'id', 'operator' => 'in', 'value' => [1, 2]]], 'sorts' => [['field' => 'id']]]);

        $this->assertSame('[{"id":1,"featured":"yes"},{"id":2,"featured":"no"}]', $this->data($response));
    }

    #[Test]
    public function it_keeps_the_casts_that_a_query_adds_to_its_rows(): void
    {
        Rest::resource('casting-shows', CastingShowController::class)->register();
        DB::table('shows')->where('id', 1)->update(['featured' => true, 'starts_at' => '2026-02-10 09:30:00']);

        $response = $this->search('/casting-shows/search', ['selects' => [['field' => 'id'], ['field' => 'featured'], ['field' => 'starts_at']], 'filters' => [['field' => 'id', 'operator' => '=', 'value' => 1]]]);

        $this->assertSame('[{"id":1,"featured":1,"starts_at":"2026-02-10"}]', $this->data($response));
    }

    #[Test]
    public function it_leaves_the_casts_of_a_rendered_model_unchanged(): void
    {
        DB::table('shows')->where('id', 1)->update(['featured' => true, 'starts_at' => '2026-02-10 09:30:00']);
        $show = UncastShow::query()->findOrFail(1);
        $casts = $show->getCasts();
        $resource = (new RestContext(RestRequest::create('/uncast-shows/search', 'POST'), $this->app))->resource(UncastShowResource::class);

        $row = RowSerializer::of(QueryPlan::bare($resource))->row($show);

        $this->assertTrue($row['featured']);
        $this->assertSame('2026-02-10T09:30:00.000000Z', $row['starts_at']);
        $this->assertSame($casts, $show->getCasts());
        $this->assertSame('2026-02-10 09:30:00', $show->toArray()['starts_at']);
    }

    #[Test]
    public function it_returns_a_boolean_and_a_date_time_of_a_pivot_without_cast_in_their_type(): void
    {
        Rest::resource('uncast-shows', UncastShowController::class)->register();
        DB::table('artists')->insert([['id' => 1, 'name' => 'Nina'], ['id' => 2, 'name' => 'Omar']]);
        DB::table('artist_show')->insert([
            ['artist_id' => 1, 'show_id' => 1, 'role' => 'lead', 'headliner' => true, 'confirmed_at' => '2026-02-01 18:00:00'],
            ['artist_id' => 2, 'show_id' => 1, 'role' => null, 'headliner' => false, 'confirmed_at' => null],
        ]);

        $response = $this->search('/uncast-shows/search', [
            'selects' => [['field' => 'id']],
            'filters' => [['field' => 'id', 'operator' => '=', 'value' => 1]],
            'includes' => [['relation' => 'artists', 'sorts' => [['field' => 'id']]]],
        ]);

        $this->assertSame('[{"id":1,"artists":[{"id":1,"name":"Nina","pivot":{"role":"lead","headliner":true,"confirmed_at":"2026-02-01T18:00:00.000000Z"}},{"id":2,"name":"Omar","pivot":{"role":null,"headliner":false,"confirmed_at":null}}],"@more":[]}]', $this->data($response));
    }

    #[Test]
    public function it_returns_the_typed_values_of_a_search_outside_http(): void
    {
        $search = Rest::search(ShowResource::class)
            ->as(null)
            ->payload(['selects' => [['field' => 'price'], ['field' => 'settings']], 'sorts' => [['field' => 'id']]]);

        $rows = '[{"price":25000.5,"settings":{"lights":"warm"}},{"price":null,"settings":{}},{"price":7,"settings":["warm","cold"]},{"price":0.1,"settings":null}]';

        $this->assertSame($rows, json_encode($search->render()['data'], JSON_THROW_ON_ERROR));
        $this->assertSame($rows, json_encode($search->lazy(3)->all(), JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $search */
    private function search(string $path, array $search): TestResponse
    {
        return $this->postJson($path, ['search' => $search])->assertOk();
    }

    private function data(TestResponse $response): string
    {
        return json_encode(json_decode((string) $response->getContent(), flags: JSON_THROW_ON_ERROR)->data, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function show(int $id, array $values): array
    {
        return [
            'id' => $id,
            'stage_code' => null,
            'title' => 'Show '.$id,
            'starts_on' => '2026-07-0'.$id,
            'status' => 'planned',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
            ...$values,
        ];
    }
}
