<?php

namespace Lomkit\Rest\Tests\Feature\Relations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Lab\Instrument;
use Lomkit\Rest\Tests\Fixtures\Lab\Lab;
use Lomkit\Rest\Tests\Fixtures\Lab\Sensor;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class JsonPivotTest extends PackageTestCase
{
    private const array WIRING = ['pins' => [1, 2], 'shielded' => true];

    private Instrument $scope;

    private Sensor $probe;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Lab];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->scope = Instrument::create(['name' => 'Scope']);
        $this->probe = Sensor::create(['name' => 'Probe']);
    }

    #[Test]
    #[DataProvider('writingOperations')]
    public function it_writes_and_reads_back_an_object_on_a_pivot_json_column_of_the_default_pivot(string $operation): void
    {
        $response = $this->mutate('sensors', $this->entry($operation, ['wiring' => self::WIRING]));

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$this->scope->id]]);
        $this->assertSame([self::WIRING], $this->storedWiring());
        $this->assertSame([['wiring' => self::WIRING]], $this->includedPivots('sensors'));
    }

    /** @return array<string, array{string}> */
    public static function writingOperations(): array
    {
        return [
            'attach' => ['attach'],
            'create' => ['create'],
            'update' => ['update'],
            'sync' => ['sync'],
            'toggle' => ['toggle'],
        ];
    }

    #[Test]
    #[DataProvider('updatingOperations')]
    public function it_updates_a_pivot_json_column_of_the_default_pivot_on_an_attached_row(string $operation): void
    {
        $this->scope->sensors()->attach($this->probe->id, ['wiring' => '{"pins": [0]}']);

        $response = $this->mutate('sensors', $this->entry($operation, ['wiring' => self::WIRING]));

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$this->scope->id]]);
        $this->assertSame([self::WIRING], $this->storedWiring());
    }

    /** @return array<string, array{string}> */
    public static function updatingOperations(): array
    {
        return [
            'update' => ['update'],
            'sync' => ['sync'],
        ];
    }

    #[Test]
    public function it_returns_the_decoded_value_of_a_pivot_json_column_of_the_default_pivot(): void
    {
        DB::table('instrument_sensor')->insert(['instrument_id' => $this->scope->id, 'sensor_id' => $this->probe->id, 'wiring' => '{"pins": [3]}']);

        $response = $this->postJson('/api/instruments/search', ['search' => ['includes' => [['relation' => 'sensors']]]]);

        $response->assertOk()->assertJsonPath('data', [[
            'id' => $this->scope->id,
            'name' => 'Scope',
            'specs' => null,
            'calibration' => null,
            'sensors' => [['id' => $this->probe->id, 'name' => 'Probe', 'pivot' => ['wiring' => ['pins' => [3]]]]],
        ]]);
    }

    #[Test]
    public function it_writes_and_reads_back_null_on_a_pivot_json_column_of_the_default_pivot(): void
    {
        $response = $this->mutate('sensors', $this->entry('attach', ['wiring' => null]));

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$this->scope->id]]);
        $this->assertSame([null], DB::table('instrument_sensor')->pluck('wiring')->all());
        $this->assertSame([['wiring' => null]], $this->includedPivots('sensors'));
    }

    #[Test]
    #[DataProvider('writingOperations')]
    public function it_leaves_the_encoding_of_a_pivot_json_column_to_the_cast_of_its_pivot_model(string $operation): void
    {
        $response = $this->mutate('mountedSensors', $this->entry($operation, ['wiring' => self::WIRING]));

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$this->scope->id]]);
        $this->assertSame([self::WIRING], $this->storedWiring());
        $this->assertSame([['wiring' => self::WIRING]], $this->includedPivots('mountedSensors'));
    }

    #[Test]
    public function it_writes_the_json_columns_of_a_model_created_through_a_relation(): void
    {
        $response = $this->postJson('/api/sensors/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['name' => 'Thermocouple'],
            'relations' => ['instruments' => [[
                'operation' => 'create',
                'attributes' => ['name' => 'Logger', 'specs' => ['ram' => 2, 'ports' => ['rs232']], 'calibration' => ['gain' => 2]],
                'pivot' => ['wiring' => self::WIRING],
            ]]],
        ]]]);

        $thermocouple = Sensor::query()->where('name', 'Thermocouple')->sole();
        $logger = Instrument::query()->where('name', 'Logger')->sole();
        $response->assertOk()->assertExactJson(['created' => [$thermocouple->id], 'updated' => []]);
        $this->assertSame(['ram' => 2, 'ports' => ['rs232']], json_decode((string) $logger->getRawOriginal('specs'), true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(['gain' => 2], json_decode((string) $logger->getRawOriginal('calibration'), true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame([self::WIRING], $this->storedWiring());
    }

    /** @param array<string, mixed> $entry */
    private function mutate(string $relation, array $entry): TestResponse
    {
        return $this->postJson('/api/instruments/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->scope->id,
            'relations' => [$relation => [$entry]],
        ]]]);
    }

    /**
     * @param  array<string, mixed>  $pivot
     * @return array<string, mixed>
     */
    private function entry(string $operation, array $pivot): array
    {
        if ($operation === 'create') {
            return ['operation' => 'create', 'attributes' => ['name' => 'Gauge'], 'pivot' => $pivot];
        }

        return ['operation' => $operation, 'key' => $this->probe->id, 'pivot' => $pivot];
    }

    /** @return list<mixed> */
    private function storedWiring(): array
    {
        return DB::table('instrument_sensor')
            ->pluck('wiring')
            ->map(fn (mixed $wiring): mixed => json_decode((string) $wiring, true, flags: JSON_THROW_ON_ERROR))
            ->all();
    }

    /** @return list<mixed> */
    private function includedPivots(string $relation): array
    {
        $response = $this->postJson('/api/instruments/search', ['search' => ['includes' => [['relation' => $relation]]]]);

        return $response->assertOk()->json('data.0.'.Str::snake($relation).'.*.pivot');
    }
}
