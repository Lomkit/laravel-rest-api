<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Lab\Lab;
use Lomkit\Rest\Tests\Fixtures\Lab\Recorder;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class JsonModelEncodingTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Lab];
    }

    #[Test]
    #[DataProvider('columnsTheModelEncodes')]
    public function it_leaves_the_encoding_of_a_created_value_to_the_cast_or_the_mutator_of_the_model(string $column, array $value): void
    {
        $response = $this->mutate(['operation' => 'create', 'attributes' => ['name' => 'Logger', $column => $value]]);

        $logger = Recorder::sole();
        $response->assertOk()->assertExactJson(['created' => [$logger->id], 'updated' => []]);
        $this->assertSame($value, $this->stored($column));

        $search = $this->postJson('/api/recorders/search');

        $search->assertOk()->assertJsonPath('data', [[
            'id' => $logger->id,
            'name' => 'Logger',
            'profile' => null,
            'samples' => null,
            'limits' => null,
            'notes' => null,
            'transcript' => null,
            $column => $value,
        ]]);
    }

    #[Test]
    #[DataProvider('columnsTheModelEncodes')]
    public function it_leaves_the_encoding_of_an_updated_value_to_the_cast_or_the_mutator_of_the_model(string $column, array $value): void
    {
        $logger = Recorder::create(['name' => 'Logger']);

        $response = $this->mutate(['operation' => 'update', 'key' => $logger->id, 'attributes' => [$column => $value]]);

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$logger->id]]);
        $this->assertSame($value, $this->stored($column));
    }

    #[Test]
    public function it_encodes_and_decodes_the_value_of_a_column_that_a_stringable_cast_of_the_model_stores_as_it_is(): void
    {
        $response = $this->mutate(['operation' => 'create', 'attributes' => ['name' => 'Logger', 'transcript' => ['lines' => 2, 'speaker' => 'Ada']]]);

        $logger = Recorder::sole();
        $response->assertOk()->assertExactJson(['created' => [$logger->id], 'updated' => []]);
        $this->assertSame(['lines' => 2, 'speaker' => 'Ada'], $this->stored('transcript'));

        $search = $this->postJson('/api/recorders/search');

        $search->assertOk()->assertJsonPath('data', [[
            'id' => $logger->id,
            'name' => 'Logger',
            'profile' => null,
            'samples' => null,
            'limits' => null,
            'notes' => null,
            'transcript' => ['lines' => 2, 'speaker' => 'Ada'],
        ]]);
    }

    /** @return array<string, array{string, array<mixed>}> */
    public static function columnsTheModelEncodes(): array
    {
        return [
            'fluent cast' => ['profile', ['ram' => 8, 'ports' => ['usb']]],
            'vector cast' => ['samples', [0.5, 1.5]],
            'cast class of the app' => ['limits', ['max' => 3]],
            'attribute with a set callback' => ['notes', ['done' => true]],
        ];
    }

    /** @param array<string, mixed> $entry */
    private function mutate(array $entry): TestResponse
    {
        return $this->postJson('/api/recorders/mutate', ['mutate' => [$entry]]);
    }

    private function stored(string $column): mixed
    {
        return json_decode((string) DB::table('recorders')->value($column), true, flags: JSON_THROW_ON_ERROR);
    }
}
