<?php

namespace Lomkit\Rest\Tests\Unit\Concerns;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Tests\Unit\Concerns\Stubs\LabelledAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MakeableTest extends TestCase
{
    #[Test]
    public function it_makes_the_class_it_is_called_on_without_arguments(): void
    {
        $action = LabelledAction::make();

        $this->assertInstanceOf(LabelledAction::class, $action);
        $this->assertSame('Archive', $action->label);
        $this->assertSame(1, $action->priority);
        $this->assertSame(Action::class, Action::make()::class);
    }

    #[Test]
    public function it_passes_positional_arguments_to_the_constructor(): void
    {
        $action = LabelledAction::make('Close', 3);

        $this->assertSame('Close', $action->label);
        $this->assertSame(3, $action->priority);
    }

    #[Test]
    public function it_passes_named_arguments_to_the_constructor(): void
    {
        $action = LabelledAction::make(priority: 5);

        $this->assertSame('Archive', $action->label);
        $this->assertSame(5, $action->priority);
    }
}
