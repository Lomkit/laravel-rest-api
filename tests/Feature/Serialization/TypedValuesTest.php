<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\ResolvedShowController;
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
