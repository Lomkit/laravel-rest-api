<?php

namespace Lomkit\Rest\Tests\Unit\Instructions;

use Lomkit\Rest\Instructions\Instruction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PublicUriKeyTest extends TestCase
{
    #[Test]
    public function it_gives_the_uri_key_of_an_instruction_of_the_resource(): void
    {
        $instruction = new Instruction;
        $instruction->name = 'With Archived';

        $this->assertSame('with-archived', $instruction->publicUriKey());
    }

    #[Test]
    public function it_prefixes_the_uri_key_with_the_name_of_the_declaring_extension(): void
    {
        $named = new Instruction;
        $named->name = 'With Archived';
        $keyed = new class extends Instruction
        {
            #[\Override]
            public function uriKey(): string
            {
                return 'as-of';
            }
        };

        $this->assertSame(['arc.with-archived', 'history.as-of'], [$named->declaredBy('arc')->publicUriKey(), $keyed->declaredBy('history')->publicUriKey()]);
    }
}
