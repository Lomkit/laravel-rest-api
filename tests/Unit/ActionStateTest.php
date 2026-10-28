<?php

namespace Lomkit\Rest\Tests\Unit;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Exceptions\InvalidActionStateException;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;
use Lomkit\Rest\Tests\TestCase;

class ActionStateTest extends TestCase
{
    public function test_action_is_not_targeted_by_default(): void
    {
        $action = Action::make()->resource(new ModelResource);

        $this->assertFalse($action->isTargeted());
        $this->assertFalse($action->jsonSerialize()['targeted']);
    }

    public function test_action_can_be_marked_targeted(): void
    {
        $action = Action::make()->resource(new ModelResource)->targeted();

        $this->assertTrue($action->isTargeted());
        $this->assertTrue($action->jsonSerialize()['targeted']);
    }

    public function test_targeted_action_cannot_become_standalone(): void
    {
        $this->expectException(InvalidActionStateException::class);

        Action::make()->targeted()->standalone();
    }

    public function test_standalone_action_cannot_become_targeted(): void
    {
        $this->expectException(InvalidActionStateException::class);

        Action::make()->standalone()->targeted();
    }
}
