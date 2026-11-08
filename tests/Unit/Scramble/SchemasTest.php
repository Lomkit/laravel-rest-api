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
}
