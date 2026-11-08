<?php

namespace Lomkit\Rest\Tests\Unit\Scramble;

use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Scramble\Schemas;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SchemasTest extends TestCase
{
    #[Test]
    public function it_lists_the_keys_of_an_object_and_accepts_the_others(): void
    {
        $this->assertSame(
            '{"type":"object","description":"A filter","properties":{"field":{"type":"string"}},"required":["field"]}',
            json_encode(Schemas::shape(['field' => new StringType], ['field'], 'A filter')->serializeAs31()),
        );
    }

    #[Test]
    public function it_refuses_the_keys_that_an_object_prohibits(): void
    {
        $this->assertSame(
            '{"type":"object","properties":{"field":{"type":"string"}},"not":{"required":["nested"]}}',
            json_encode(Schemas::shape(['field' => new StringType], [], '', ['nested'])->serializeAs31()),
        );
        $this->assertSame(
            '{"type":"object","properties":{"nested":{"type":"string"}},"not":{"anyOf":[{"required":["field"]},{"required":["relation"]}]}}',
            json_encode(Schemas::shape(['nested' => new StringType], [], '', ['field', 'relation'])->serializeAs32()),
        );
    }
}
