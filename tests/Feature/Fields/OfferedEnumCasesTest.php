<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Fields\Stubs\OfferedShowController;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Festival\Festival;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

class OfferedEnumCasesTest extends PackageTestCase
{
    use AssertsExactErrors;

    private int $soldOut;

    private int $planned;

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
        Rest::resource('offered-shows', OfferedShowController::class)->register();
        DB::table('stages')->insert([['code' => 'main', 'name' => 'Main stage']]);
        DB::table('artists')->insert([['id' => 1, 'name' => 'Nina']]);
        $this->soldOut = DB::table('shows')->insertGetId(['stage_code' => 'main', 'title' => 'Opening night', 'starts_on' => '2026-07-01', 'status' => 'sold_out', 'level' => 3]);
        $this->planned = DB::table('shows')->insertGetId(['stage_code' => 'main', 'title' => 'Closing night', 'starts_on' => '2026-07-03', 'status' => 'planned', 'level' => 1]);
    }

    #[Test]
    public function it_creates_a_row_with_an_offered_case(): void
    {
        $response = $this->postJson('/api/shows/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['title' => 'Late show', 'starts_on' => '2026-07-04', 'status' => 'on_sale']],
        ]]);

        $response->assertOk();
        $this->assertSame('on_sale', DB::table('shows')->where('title', 'Late show')->value('status'));
    }

    #[Test]
    public function it_refuses_a_withdrawn_case_on_create(): void
    {
        $response = $this->postJson('/api/shows/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['title' => 'Late show', 'starts_on' => '2026-07-04', 'status' => 'sold_out']],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.attributes.status' => ['The selected mutate.0.attributes.status is invalid.']]);
        $this->assertSame(2, DB::table('shows')->count());
    }

    #[Test]
    public function it_refuses_a_withdrawn_case_on_update_and_keeps_the_stored_one(): void
    {
        $response = $this->postJson('/api/shows/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->planned, 'attributes' => ['status' => 'sold_out']],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.attributes.status' => ['The selected mutate.0.attributes.status is invalid.']]);
        $this->assertSame('planned', DB::table('shows')->where('id', $this->planned)->value('status'));
    }

    #[Test]
    public function it_updates_a_row_that_holds_a_withdrawn_case_without_writing_it(): void
    {
        $response = $this->postJson('/api/shows/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->soldOut, 'attributes' => ['title' => 'First night']],
        ]]);

        $response->assertOk();
        $this->assertSame(['First night', 'sold_out'], array_values((array) DB::table('shows')->where('id', $this->soldOut)->first(['title', 'status'])));
    }

    #[Test]
    public function it_refuses_a_withdrawn_case_in_a_nested_entry(): void
    {
        $response = $this->postJson('/api/stages/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 'main', 'relations' => ['shows' => [
                ['operation' => 'create', 'attributes' => ['title' => 'Late show', 'starts_on' => '2026-07-04', 'status' => 'sold_out']],
            ]]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations.shows.0.attributes.status' => ['The selected mutate.0.relations.shows.0.attributes.status is invalid.']]);
    }

    #[Test]
    public function it_attaches_with_an_offered_pivot_case(): void
    {
        $response = $this->postJson('/offered-shows/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->planned, 'relations' => ['artists' => [['operation' => 'attach', 'key' => 1, 'pivot' => ['role' => 'guest']]]]],
        ]]);

        $response->assertOk();
        $this->assertSame('guest', DB::table('artist_show')->where('show_id', $this->planned)->value('role'));
    }

    #[Test]
    public function it_refuses_a_pivot_case_that_the_closure_withdraws(): void
    {
        $response = $this->postJson('/offered-shows/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->planned, 'relations' => ['artists' => [['operation' => 'attach', 'key' => 1, 'pivot' => ['role' => 'support']]]]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations.artists.0.pivot.role' => ['The selected mutate.0.relations.artists.0.pivot.role is invalid.']]);
        $this->assertSame(0, DB::table('artist_show')->count());
    }

    #[Test]
    public function it_filters_and_reads_rows_on_a_withdrawn_case(): void
    {
        $response = $this->postJson('/api/shows/search', ['search' => [
            'filters' => [['field' => 'status', 'operator' => 'in', 'value' => ['sold_out']]],
            'selects' => [['field' => 'id'], ['field' => 'status']],
        ]]);

        $response->assertOk();
        $this->assertSame([['id' => $this->soldOut, 'status' => 'sold_out']], $response->json('data'));
    }

    #[Test]
    public function it_runs_an_action_with_an_offered_case(): void
    {
        $response = $this->postJson('/offered-shows/actions/regrade-shows', ['resources' => [$this->planned], 'fields' => [['name' => 'level', 'value' => 2]]]);

        $response->assertOk();
        $this->assertSame(2, Show::query()->findOrFail($this->planned)->getRawOriginal('level'));
    }

    #[Test]
    public function it_refuses_an_action_field_case_that_is_not_offered(): void
    {
        $response = $this->postJson('/offered-shows/actions/regrade-shows', ['resources' => [$this->planned], 'fields' => [['name' => 'level', 'value' => 3]]]);

        $this->assertExactErrors($response, ['fields.level' => ['The selected level is invalid.']]);
        $this->assertSame(1, Show::query()->findOrFail($this->planned)->getRawOriginal('level'));
    }

    #[Test]
    #[WithConfig('rest.precognition.enabled', true)]
    public function it_refuses_a_withdrawn_case_in_a_precognitive_request(): void
    {
        $response = $this->postJson('/api/shows/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->planned, 'attributes' => ['status' => 'sold_out']],
        ]], ['Precognition' => 'true', 'Precognition-Validate-Only' => 'mutate.0.attributes.status']);

        $this->assertExactErrors($response, ['mutate.0.attributes.status' => ['The selected mutate.0.attributes.status is invalid.']]);
    }

    #[Test]
    public function it_describes_the_offered_cases_next_to_every_case(): void
    {
        $response = $this->getJson('/offered-shows?with=actions.fields,relations.pivot_fields');

        $response->assertOk();
        $fields = array_column($response->json('data.fields'), null, 'name');
        $this->assertSame(['name' => 'status', 'label' => null, 'type' => 'enum', 'nullable' => false, 'values' => ['planned', 'on_sale', 'sold_out'], 'offered' => ['planned', 'on_sale']], $fields['status']);
        $this->assertSame(['name' => 'level', 'label' => null, 'type' => 'enum', 'nullable' => false, 'values' => [1, 2, 3]], $fields['level']);
        $this->assertSame([1, 2, 3], $response->json('data.actions.0.fields.0.values'));
        $this->assertSame([1, 2], $response->json('data.actions.0.fields.0.offered'));
        $this->assertSame(['lead', 'guest', 'support'], $response->json('data.relations.0.pivot_fields.0.values'));
        $this->assertSame(['lead', 'guest'], $response->json('data.relations.0.pivot_fields.0.offered'));
    }
}
