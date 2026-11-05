<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Concerns\HasInputFields;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HasInputFieldsTest extends TestCase
{
    #[Test]
    public function it_declares_no_fields_by_default(): void
    {
        $owner = new class
        {
            use HasInputFields;
        };

        $this->assertSame([], $owner->fields());
        $this->assertCount(0, $owner->getFields());
    }

    #[Test]
    public function it_builds_a_new_field_set_on_each_call(): void
    {
        $owner = new class
        {
            use HasInputFields;

            public int $calls = 0;

            public function fields(): array
            {
                $this->calls++;

                return [Text::make('title'), Integer::make('min_views')];
            }
        };

        $first = $owner->getFields();
        $second = $owner->getFields();

        $this->assertSame(['title', 'min_views'], $first->names());
        $this->assertNotSame($first, $second);
        $this->assertSame(2, $owner->calls);
    }

    #[Test]
    public function it_names_its_owner_when_the_fields_are_invalid(): void
    {
        $owner = new class
        {
            use HasInputFields;

            public function fields(): array
            {
                return [Text::make('title'), Text::make('title')];
            }
        };

        $this->expectExceptionObject(InvalidFieldException::duplicateName($owner::class.'::fields()', 'title'));

        $owner->getFields();
    }

    #[Test]
    public function it_names_its_owner_when_an_input_field_declares_write_rules(): void
    {
        $owner = new class
        {
            use HasInputFields;

            public function fields(): array
            {
                return [Text::make('title')->updateRules('max:1')];
            }
        };

        $this->expectExceptionObject(InvalidFieldException::writeRulesOnInput($owner::class.'::fields()', 'title'));

        $owner->getFields();
    }
}
