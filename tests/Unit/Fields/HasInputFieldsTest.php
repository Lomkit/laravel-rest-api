<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\ResourceDefinition;
use Lomkit\Rest\Instructions\Instruction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HasInputFieldsTest extends TestCase
{
    #[Test]
    public function it_declares_no_fields_by_default(): void
    {
        $action = new Action;
        $instruction = new Instruction;

        $this->assertSame([], $action->fields());
        $this->assertSame([], $instruction->fields());
        $this->assertCount(0, self::definition()->inputFields($action));
        $this->assertCount(0, self::definition()->inputFields($instruction));
    }

    #[Test]
    public function it_reads_the_fields_of_a_declaration_once_per_definition(): void
    {
        $action = new class extends Action
        {
            public int $calls = 0;

            #[\Override]
            public function fields(): array
            {
                $this->calls++;

                return [Text::make('title'), Integer::make('min_views')];
            }
        };
        $definition = self::definition();

        $first = $definition->inputFields($action);
        $second = $definition->inputFields($action);
        $other = self::definition()->inputFields($action);

        $this->assertSame(['title', 'min_views'], $first->names());
        $this->assertSame($first, $second);
        $this->assertNotSame($first, $other);
        $this->assertSame(2, $action->calls);
    }

    #[Test]
    public function it_names_its_owner_when_the_fields_are_invalid(): void
    {
        $action = new class extends Action
        {
            #[\Override]
            public function fields(): array
            {
                return [Text::make('title'), Text::make('title')];
            }
        };

        $this->expectExceptionObject(InvalidFieldException::duplicateName($action::class.'::fields()', 'title'));

        self::definition()->inputFields($action);
    }

    #[Test]
    public function it_names_its_owner_when_an_input_field_declares_write_rules(): void
    {
        $instruction = new class extends Instruction
        {
            #[\Override]
            public function fields(): array
            {
                return [Text::make('title')->updateRules('max:1')];
            }
        };

        $this->expectExceptionObject(InvalidFieldException::writeRulesOnInput($instruction::class.'::fields()', 'title'));

        self::definition()->inputFields($instruction);
    }

    private static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(new Resource, new RestRequest);
    }
}
