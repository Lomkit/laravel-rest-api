<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Actions\ScaffoldedAction;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\Details\InputFieldDetails;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;

class MakeActionCommandTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_writes_an_action_class_that_declares_typed_fields(): void
    {
        $this->artisan('rest:action', ['name' => 'ScaffoldedAction'])->assertSuccessful();

        $path = app_path('Rest/Actions/ScaffoldedAction.php');
        $this->assertFileExists($path);
        require_once $path;

        $action = new ScaffoldedAction;

        $this->assertInstanceOf(Action::class, $action);
        $this->assertSame(
            [['name' => 'id', 'type' => 'id', 'nullable' => false, 'default' => null, 'rules' => ['integer', 'required'], 'meta' => []]],
            InputFieldDetails::forFields($action->getFields(new RestRequest)),
        );
    }
}
