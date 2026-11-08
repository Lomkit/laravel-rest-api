<?php

namespace Lomkit\Rest\Tests\Unit\Scramble;

use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Scramble\Schemas;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SchemasTest extends TestCase
{
    #[Test]
    public function it_closes_an_object_to_the_keys_that_it_lists(): void
    {
        $this->assertSame(
            '{"type":"object","description":"A filter","properties":{"field":{"type":"string"}},"required":["field"],"additionalProperties":false}',
            json_encode(Schemas::closed(['field' => new StringType], ['field'], 'A filter')->serializeAs31()),
        );
    }

    #[Test]
    public function it_leaves_an_object_open_and_refuses_the_keys_that_it_prohibits(): void
    {
        $this->assertSame('{"type":"object","properties":{"field":{"type":"string"}}}', json_encode(Schemas::open(['field' => new StringType])->serializeAs31()));
        $this->assertSame(
            '{"type":"object","properties":{"field":{"type":"string"}},"not":{"required":["text"]}}',
            json_encode(Schemas::open(['field' => new StringType], ['text'])->serializeAs31()),
        );
        $this->assertSame(
            '{"type":"object","not":{"anyOf":[{"required":["text"]},{"required":["trashed"]}]}}',
            json_encode(Schemas::open([], ['text', 'trashed'])->serializeAs32()),
        );
    }
}
