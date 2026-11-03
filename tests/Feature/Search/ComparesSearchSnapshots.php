<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

trait ComparesSearchSnapshots
{
    protected function assertMatchesSnapshot(string $snapshot, TestResponse $response): void
    {
        $contents = file_get_contents(__DIR__."/Snapshots/Characterization/{$snapshot}.json");
        $this->assertIsString($contents);

        $expected = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($expected);

        $driver = DB::connection()->getDriverName();
        $this->assertSame($expected['queries'][$driver] ?? null, count(DB::getQueryLog()));
        $this->assertSame($expected['shape'], $this->shape($response->json()));

        if ($driver !== 'sqlite') {
            return;
        }

        $this->assertSame($expected['response'], $response->json());
        $this->assertSame($expected['raw_queries'], array_column(DB::getRawQueryLog(), 'raw_query'));
    }

    protected function shape(mixed $json, int|string|null $key = null): mixed
    {
        if (! is_array($json)) {
            return $key === 'id' ? $json : null;
        }

        $shape = [];

        foreach ($json as $name => $value) {
            $shape[$name] = $this->shape($value, $name);
        }

        return $shape;
    }
}
