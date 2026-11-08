<?php

namespace Lomkit\Rest\Tests\Unit\Scramble;

use Dedoc\Scramble\Support\Generator\Discriminator;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Scramble\Types\OneOf;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OneOfTest extends TestCase
{
    #[Test]
    public function it_lists_its_types_with_their_discriminator(): void
    {
        $oneOf = new OneOf([new StringType, new IntegerType], new Discriminator('kind', ['text' => '#/components/schemas/Text']));
        $oneOf->setDescription('A text or a number');

        $this->assertSame(
            '{"description":"A text or a number","discriminator":{"propertyName":"kind","mapping":{"text":"#\/components\/schemas\/Text"}},"oneOf":[{"type":"string"},{"type":"integer"}]}',
            json_encode($oneOf->serializeAs31()),
        );
        $this->assertSame(json_encode($oneOf->serializeAs31()), json_encode($oneOf->serializeAs32()));
    }

    #[Test]
    public function it_accepts_null_when_it_is_nullable(): void
    {
        $oneOf = new OneOf([new StringType, new IntegerType]);
        $oneOf->nullable(true);

        $this->assertSame('{"anyOf":[{"oneOf":[{"type":"string"},{"type":"integer"}]},{"type":"null"}]}', json_encode($oneOf->serializeAs31()));
    }
}
