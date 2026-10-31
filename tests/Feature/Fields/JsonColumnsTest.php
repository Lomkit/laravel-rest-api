<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Lab\Instrument;
use Lomkit\Rest\Tests\Fixtures\Lab\Lab;
use Lomkit\Rest\Tests\Fixtures\Lab\Sensor;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class JsonColumnsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Lab];
    }

    #[Test]
    public function it_writes_and_reads_back_an_object_on_a_json_column_the_model_does_not_cast(): void
    {
        $response = $this->mutate(['operation' => 'create', 'attributes' => ['name' => 'Scope', 'specs' => ['ram' => 8, 'ports' => ['usb', 'hdmi']]]]);

        $scope = Instrument::sole();
        $response->assertOk()->assertExactJson(['created' => [$scope->id], 'updated' => []]);
        $this->assertSame(['ram' => 8, 'ports' => ['usb', 'hdmi']], $this->stored('specs'));

        $search = $this->postJson('/api/instruments/search');

        $search->assertOk()->assertJsonPath('data', [
            ['id' => $scope->id, 'name' => 'Scope', 'specs' => ['ram' => 8, 'ports' => ['usb', 'hdmi']], 'calibration' => null],
        ]);
    }

    #[Test]
    public function it_updates_a_json_column_the_model_does_not_cast(): void
    {
        $scope = Instrument::create(['name' => 'Scope', 'specs' => '{"ram": 4}']);

        $response = $this->mutate(['operation' => 'update', 'key' => $scope->id, 'attributes' => ['specs' => ['ram' => 16, 'ports' => []]]]);

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$scope->id]]);
        $this->assertSame(['ram' => 16, 'ports' => []], $this->stored('specs'));
    }

    #[Test]
    public function it_returns_the_decoded_value_of_a_json_column_the_model_does_not_cast(): void
    {
        $scope = Instrument::create(['name' => 'Scope', 'specs' => '{"ram": 8, "ports": ["usb"]}']);

        $response = $this->postJson('/api/instruments/search');

        $response->assertOk()->assertJsonPath('data', [
            ['id' => $scope->id, 'name' => 'Scope', 'specs' => ['ram' => 8, 'ports' => ['usb']], 'calibration' => null],
        ]);
    }

    #[Test]
    public function it_writes_and_reads_back_null_on_a_json_column_the_model_does_not_cast(): void
    {
        $response = $this->mutate(['operation' => 'create', 'attributes' => ['name' => 'Scope', 'specs' => null]]);

        $scope = Instrument::sole();
        $response->assertOk()->assertExactJson(['created' => [$scope->id], 'updated' => []]);
        $this->assertNull(DB::table('instruments')->value('specs'));

        $search = $this->postJson('/api/instruments/search');

        $search->assertOk()->assertJsonPath('data', [
            ['id' => $scope->id, 'name' => 'Scope', 'specs' => null, 'calibration' => null],
        ]);
    }

    #[Test]
    public function it_leaves_the_encoding_of_a_json_column_to_its_cast(): void
    {
        $response = $this->mutate(['operation' => 'create', 'attributes' => ['name' => 'Scope', 'calibration' => ['gain' => 1.5, 'offset' => 2]]]);

        $scope = Instrument::sole();
        $response->assertOk()->assertExactJson(['created' => [$scope->id], 'updated' => []]);
        $this->assertSame(['gain' => 1.5, 'offset' => 2], $this->stored('calibration'));
        $this->assertSame(['gain' => 1.5, 'offset' => 2], $scope->calibration);

        $search = $this->postJson('/api/instruments/search');

        $search->assertOk()->assertJsonPath('data', [
            ['id' => $scope->id, 'name' => 'Scope', 'specs' => null, 'calibration' => ['gain' => 1.5, 'offset' => 2]],
        ]);
    }

    #[Test]
    public function it_returns_the_decoded_json_columns_of_an_included_model_and_of_its_pivot(): void
    {
        $scope = Instrument::create(['name' => 'Scope', 'specs' => '{"ram": 8}']);
        $probe = Sensor::create(['name' => 'Probe']);
        DB::table('instrument_sensor')->insert(['instrument_id' => $scope->id, 'sensor_id' => $probe->id, 'wiring' => '{"pins": [3]}']);

        $response = $this->postJson('/api/sensors/search', ['search' => ['includes' => [['relation' => 'instruments']]]]);

        $response->assertOk()->assertJsonPath('data', [[
            'id' => $probe->id,
            'name' => 'Probe',
            'instruments' => [[
                'id' => $scope->id,
                'name' => 'Scope',
                'specs' => ['ram' => 8],
                'calibration' => null,
                'pivot' => ['wiring' => ['pins' => [3]]],
            ]],
        ]]);
    }

    #[Test]
    public function it_returns_an_aggregate_aliased_as_a_json_field_as_the_query_computes_it(): void
    {
        $scope = Instrument::create(['name' => 'Scope']);
        $scope->sensors()->attach(Sensor::create(['name' => 'Probe'])->id);

        $response = $this->postJson('/api/instruments/search', ['search' => [
            'selects' => [['field' => 'id']],
            'aggregates' => [['relation' => 'sensors', 'type' => 'max', 'field' => 'name', 'alias' => 'specs']],
        ]]);

        $response->assertOk()->assertJsonPath('data', [['id' => $scope->id, 'specs' => 'Probe']]);
    }

    #[Test]
    #[DataProvider('uncastJsonFields')]
    public function it_refuses_a_filter_on_a_json_field_the_model_does_not_cast(string $field): void
    {
        $response = $this->postJson('/api/instruments/search', ['search' => ['filters' => [['field' => $field, 'value' => 'usb']]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.filters.0.field' => ['The selected search.filters.0.field is invalid.'],
        ]);
    }

    /** @return array<string, array{string}> */
    public static function uncastJsonFields(): array
    {
        return [
            'a column of the model' => ['specs'],
            'a column of the default pivot' => ['sensors.pivot.wiring'],
        ];
    }

    /** @param array<string, mixed> $entry */
    private function mutate(array $entry): TestResponse
    {
        return $this->postJson('/api/instruments/mutate', ['mutate' => [$entry]]);
    }

    private function stored(string $column): mixed
    {
        return json_decode((string) DB::table('instruments')->value($column), true, flags: JSON_THROW_ON_ERROR);
    }
}
