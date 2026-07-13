<?php

namespace Lomkit\Rest\Tests\Unit;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Exceptions\InvalidActionStateException;
use Lomkit\Rest\Tests\TestCase;

class ActionStateTest extends TestCase
{
    public function test_action_is_not_restricted_by_default(): void
    {
        $action = Action::make();

        $this->assertFalse($action->isRestricted());
        $this->assertFalse($action->jsonSerialize()['restricted']);
    }

    public function test_action_can_be_marked_restricted(): void
    {
        $action = Action::make()->restricted();

        $this->assertTrue($action->isRestricted());
        $this->assertTrue($action->jsonSerialize()['restricted']);
    }

    public function test_restricted_action_cannot_become_standalone(): void
    {
        $this->expectException(InvalidActionStateException::class);

        Action::make()->restricted()->standalone();
    }

    public function test_standalone_action_cannot_become_restricted(): void
    {
        $this->expectException(InvalidActionStateException::class);

        Action::make()->standalone()->restricted();
    }
}
