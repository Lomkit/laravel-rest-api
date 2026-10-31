<?php

namespace Lomkit\Rest\Tests\Feature\Relations;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Lab\Instrument;
use Lomkit\Rest\Tests\Fixtures\Lab\Lab;
use Lomkit\Rest\Tests\Fixtures\Lab\Sensor;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SharedJsonPivotFieldTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Lab];
    }

    #[Test]
    #[DataProvider('relationOrders')]
    public function it_encodes_a_pivot_field_shared_by_two_relations_by_the_casts_of_each_pivot_class(string $first, string $second): void
    {
        $scope = Instrument::create(['name' => 'Scope']);
        $probe = Sensor::create(['name' => 'Probe']);
        $gauge = Sensor::create(['name' => 'Gauge']);
        $entries = [
            'sensors' => [['operation' => 'attach', 'key' => $probe->id, 'pivot' => ['wiring' => ['pins' => [1]]]]],
            'mountedSensors' => [['operation' => 'attach', 'key' => $gauge->id, 'pivot' => ['wiring' => ['pins' => [2]]]]],
        ];

        $response = $this->postJson('/api/instruments/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $scope->id,
            'relations' => [$first => $entries[$first], $second => $entries[$second]],
        ]]]);

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$scope->id]]);
        $this->assertSame([['pins' => [1]], ['pins' => [2]]], $this->storedWiring());
    }

    /** @return array<string, array{string, string}> */
    public static function relationOrders(): array
    {
        return [
            'default pivot first' => ['sensors', 'mountedSensors'],
            'casting pivot first' => ['mountedSensors', 'sensors'],
        ];
    }

    #[Test]
    public function it_decodes_a_pivot_field_shared_by_two_relations_by_the_casts_of_each_pivot_class(): void
    {
        $scope = Instrument::create(['name' => 'Scope']);
        $meter = Instrument::create(['name' => 'Meter']);
        $probe = Sensor::create(['name' => 'Probe']);
        DB::table('instrument_sensor')->insert([
            ['instrument_id' => $scope->id, 'sensor_id' => $probe->id, 'wiring' => '{"pins": [1]}'],
            ['instrument_id' => $meter->id, 'sensor_id' => $probe->id, 'wiring' => '{"pins": [2]}'],
        ]);

        $response = $this->postJson('/api/instruments/search', ['search' => [
            'sorts' => [['field' => 'id']],
            'includes' => [['relation' => 'sensors'], ['relation' => 'mountedSensors']],
        ]]);

        $response->assertOk();
        $this->assertSame([['wiring' => ['pins' => [1]]], ['wiring' => ['pins' => [2]]]], $response->json('data.*.sensors.0.pivot'));
        $this->assertSame([['wiring' => ['pins' => [1]]], ['wiring' => ['pins' => [2]]]], $response->json('data.*.mounted_sensors.0.pivot'));
    }

    /** @return list<mixed> */
    private function storedWiring(): array
    {
        return DB::table('instrument_sensor')
            ->orderBy('sensor_id')
            ->pluck('wiring')
            ->map(fn (mixed $wiring): mixed => json_decode((string) $wiring, true, flags: JSON_THROW_ON_ERROR))
            ->all();
    }
}
