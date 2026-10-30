<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\NumberedFieldsAction;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\NumberedFieldsPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class NumericInputNameTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_rejects_a_scope_parameter_with_a_numeric_name(): void
    {
        $this->expectExceptionObject(InvalidFieldException::numericInputName("Scope::make('ranked')->fields()", '0'));
        $this->expectExceptionMessage("Scope::make('ranked')->fields() declares the input field [0] with a numeric name. Input names must not be numeric.");

        Scope::make('ranked')->fields([Integer::make('0')]);
    }

    #[Test]
    public function it_rejects_an_action_field_with_a_numeric_name_when_the_action_runs(): void
    {
        Rest::resource('numbered-posts', NumberedFieldsPostController::class)->register();
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidFieldException::numericInputName(NumberedFieldsAction::class.'::fields()', '2'));
        $this->expectExceptionMessage(NumberedFieldsAction::class.'::fields() declares the input field [2] with a numeric name. Input names must not be numeric.');

        $this->postJson('/numbered-posts/actions/numbered-fields', ['fields' => [['name' => 'rank', 'value' => 1]]]);
    }
}
