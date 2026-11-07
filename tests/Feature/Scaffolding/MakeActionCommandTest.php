<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Actions\ScaffoldedAction;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

class MakeActionCommandTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_writes_an_action_class_without_input_fields(): void
    {
        $this->artisan('rest:action', ['name' => 'ScaffoldedAction'])
            ->expectsOutputToContain('Action [app/Rest/Actions/ScaffoldedAction.php] created successfully.')
            ->assertSuccessful();

        $this->loadGeneratedClass(ScaffoldedAction::class);

        $action = new ScaffoldedAction;

        $this->assertInstanceOf(Action::class, $action);
        $this->assertSame([], $action->fields());
        $this->assertSame(ActionTarget::Search, $action->target);
        $this->assertSame(ActionFields::class, (string) (new ReflectionMethod($action, 'handle'))->getParameters()[0]->getType());
    }
}
