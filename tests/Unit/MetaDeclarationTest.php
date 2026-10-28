<?php

namespace Lomkit\Rest\Tests\Unit;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Instructions\Instruction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MetaDeclarationTest extends TestCase
{
    #[Test]
    public function it_merges_the_meta_of_an_action_with_later_keys_winning(): void
    {
        $action = Action::make()->meta(['color' => '#FFFFFF', 'icon' => 'send'])->meta(['color' => '#000000']);

        $this->assertSame(['color' => '#000000', 'icon' => 'send'], $action->getMeta());
        $this->assertSame([], Action::make()->getMeta());
    }

    #[Test]
    public function it_merges_the_meta_of_an_instruction_with_later_keys_winning(): void
    {
        $instruction = Instruction::make()->meta(['group' => 'ranking'])->meta(['hint' => 'Featured first']);

        $this->assertSame(['group' => 'ranking', 'hint' => 'Featured first'], $instruction->getMeta());
        $this->assertSame([], Instruction::make()->getMeta());
    }
}
