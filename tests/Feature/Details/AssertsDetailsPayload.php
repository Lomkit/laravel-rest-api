<?php

namespace Lomkit\Rest\Tests\Feature\Details;

use Illuminate\Testing\TestResponse;

trait AssertsDetailsPayload
{
    private const string EVERY_PART = 'fields.capabilities,fields.filter,fields.rules,fields.meta,relations.constraints,relations.operations,relations.pivot_fields,actions.fields,actions.authorized,actions.meta,metrics.authorized,metrics.meta,instructions.fields,instructions.meta,scopes.fields,scopes.meta,rules,text,gates,authorizations';

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
