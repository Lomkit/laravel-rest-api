<?php

namespace Lomkit\Rest\Tests\Feature\Details;

use Illuminate\Testing\TestResponse;

trait AssertsDetailsPayload
{
    protected function assertDetailsPayload(string $payload, TestResponse $response): void
    {
        $response->assertOk();

        $expected = file_get_contents(__DIR__."/Payloads/{$payload}.json");

        $this->assertIsString($expected);
        $this->assertSame(
            json_encode(json_decode($expected, flags: JSON_THROW_ON_ERROR)),
            json_encode(json_decode((string) $response->getContent(), flags: JSON_THROW_ON_ERROR)),
        );
    }
}
