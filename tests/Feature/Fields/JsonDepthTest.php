<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\PayloadPostController;
use Lomkit\Rest\Tests\Fixtures\Lab\Instrument;
use Lomkit\Rest\Tests\Fixtures\Lab\Lab;
use Lomkit\Rest\Tests\Fixtures\Lab\Sensor;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class JsonDepthTest extends PackageTestCase
{
    private ReceivedFields $received;

    private Instrument $scope;

    private Sensor $probe;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Lab, new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        Rest::resource('payload-posts', PayloadPostController::class)->register();
        $this->scope = Instrument::create(['name' => 'Scope', 'specs' => '{"ram": 4}']);
        $this->probe = Sensor::create(['name' => 'Probe']);
    }

    #[Test]
    #[DataProvider('rootOperations')]
    public function it_refuses_a_json_attribute_one_level_deeper_than_the_limit(string $operation): void
    {
        $response = $this->postJson('/api/instruments/mutate', ['mutate' => [$this->rootEntry($operation, self::nested(65))]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.specs' => ['The mutate.0.attributes.specs field goes deeper than the limit of 64 levels.'],
        ]);
        $this->assertSame([['Scope', ['ram' => 4]]], $this->instruments());
    }

    #[Test]
    #[DataProvider('rootOperations')]
    public function it_writes_a_json_attribute_at_the_limit(string $operation): void
    {
        $response = $this->postJson('/api/instruments/mutate', ['mutate' => [$this->rootEntry($operation, self::nested(64))]]);

        $gauge = Instrument::query()->where('name', 'Gauge')->sole();
        $response->assertOk()->assertExactJson(['operations' => [['operation' => $operation, 'key' => $gauge->id]]]);
        $this->assertSame(self::nested(64), self::decoded(DB::table('instruments')->where('id', $gauge->id)->value('specs')));
    }

    /** @return array<string, array{string}> */
    public static function rootOperations(): array
    {
        return [
            'create' => ['create'],
            'update' => ['update'],
        ];
    }

    #[Test]
    public function it_refuses_a_json_attribute_deeper_than_mysql_stores(): void
    {
        $response = $this->postJson('/api/instruments/mutate', ['mutate' => [$this->rootEntry('update', self::nested(101))]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.specs' => ['The mutate.0.attributes.specs field goes deeper than the limit of 64 levels.'],
        ]);
        $this->assertSame([['Scope', ['ram' => 4]]], $this->instruments());
    }

    #[Test]
    #[DataProvider('nestedEntries')]
    public function it_refuses_a_json_value_one_level_deeper_than_the_limit_in_a_nested_entry(string $operation, string $key): void
    {
        $entry = $operation === 'create'
            ? ['operation' => 'create', 'attributes' => ['name' => 'Gauge', 'specs' => self::nested(65)]]
            : ['operation' => 'attach', 'key' => $this->scope->id, 'pivot' => ['wiring' => self::nested(65)]];

        $response = $this->postJson('/api/sensors/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->probe->id, 'relations' => ['instruments' => [$entry]]],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [$key => ["The {$key} field goes deeper than the limit of 64 levels."]]);
        $this->assertSame([['Scope', ['ram' => 4]]], $this->instruments());
        $this->assertSame(0, DB::table('instrument_sensor')->count());
    }

    /** @return array<string, array{string, string}> */
    public static function nestedEntries(): array
    {
        return [
            'an attribute of a created entry' => ['create', 'mutate.0.relations.instruments.0.attributes.specs'],
            'a pivot value of an attached entry' => ['attach', 'mutate.0.relations.instruments.0.pivot.wiring'],
        ];
    }

    #[Test]
    public function it_writes_a_pivot_json_value_at_the_limit(): void
    {
        $response = $this->postJson('/api/sensors/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->probe->id,
            'relations' => ['instruments' => [['operation' => 'attach', 'key' => $this->scope->id, 'pivot' => ['wiring' => self::nested(64)]]]],
        ]]]);

        $response->assertOk();
        $this->assertSame(self::nested(64), self::decoded(DB::table('instrument_sensor')->value('wiring')));
    }

    #[Test]
    #[DataProvider('payloadDepths')]
    public function it_bounds_the_depth_of_a_json_action_field(int $levels, ?string $message): void
    {
        $response = $this->postJson('/payload-posts/actions/attach-payload', ['fields' => [['name' => 'payload', 'value' => self::nested($levels)]]]);

        if ($message === null) {
            $response->assertOk();
            $this->assertSame([['handle', ['payload' => self::nested($levels)]]], $this->received->calls());

            return;
        }

        $response->assertUnprocessable()->assertJsonPath('errors', ['fields.payload' => [$message]]);
        $this->assertSame([], $this->received->calls());
    }

    #[Test]
    #[DataProvider('payloadDepths')]
    public function it_bounds_the_depth_of_a_json_instruction_field(int $levels, ?string $message): void
    {
        $response = $this->postJson('/payload-posts/search', ['search' => ['instructions' => [
            ['name' => 'with-payload', 'fields' => [['name' => 'payload', 'value' => self::nested($levels)]]],
        ]]]);

        if ($message === null) {
            $response->assertOk();
            $this->assertSame([['handle', ['payload' => self::nested($levels)]]], $this->received->calls());

            return;
        }

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.instructions.0.fields.payload' => [$message]]);
        $this->assertSame([], $this->received->calls());
    }

    /** @return array<string, array{int, ?string}> */
    public static function payloadDepths(): array
    {
        return [
            'at the limit' => [64, null],
            'one level deeper' => [65, 'The payload field goes deeper than the limit of 64 levels.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $specs
     * @return array<string, mixed>
     */
    private function rootEntry(string $operation, array $specs): array
    {
        $key = $operation === 'update' ? ['key' => $this->scope->id] : [];

        return ['operation' => $operation, ...$key, 'attributes' => ['name' => 'Gauge', 'specs' => $specs]];
    }

    /** @return array<string, mixed> */
    private static function nested(int $levels): array
    {
        $value = ['depth' => $levels];

        for ($level = 1; $level < $levels; $level++) {
            $value = ['child' => $value];
        }

        return $value;
    }

    private static function decoded(mixed $stored): mixed
    {
        return json_decode((string) $stored, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return list<array{mixed, mixed}> */
    private function instruments(): array
    {
        return DB::table('instruments')
            ->orderBy('id')
            ->get(['name', 'specs'])
            ->map(fn (object $row): array => [$row->name, self::decoded($row->specs)])
            ->all();
    }
}
