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
    public function it_keeps_the_owner_of_an_instruction_out_of_its_public_methods(): void
    {
        $this->assertFalse(method_exists(Instruction::class, 'declaredBy'));
    }
}
