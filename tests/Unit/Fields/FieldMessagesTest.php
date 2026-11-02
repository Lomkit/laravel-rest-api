<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Lomkit\Rest\Fields\Text;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldMessagesTest extends TestCase
{
    #[Test]
    public function it_has_no_label_and_no_messages_by_default(): void
    {
        $field = Text::make('email');

        $this->assertNull($field->getLabel());
        $this->assertSame([], $field->getMessages());
    }

    #[Test]
    public function it_has_no_label_when_the_label_is_blank(): void
    {
        $field = Text::make('email')
            ->label('email address')
            ->label('  ');

        $this->assertNull($field->getLabel());
    }

    #[Test]
    public function it_keeps_the_last_label(): void
    {
        $field = Text::make('email')
            ->label('email')
            ->label('email address');

        $this->assertSame('email address', $field->getLabel());
    }

    #[Test]
    public function it_merges_the_messages(): void
    {
        $field = Text::make('email')
            ->messages(['required' => 'A', 'max' => 'B'])
            ->messages(['max' => 'C']);

        $this->assertSame(['required' => 'A', 'max' => 'C'], $field->getMessages());
    }

    #[Test]
    public function it_returns_the_field_from_each_setter(): void
    {
        $field = Text::make('email');

        $this->assertSame($field, $field->label('email address'));
        $this->assertSame($field, $field->messages(['required' => 'A']));
    }

    #[Test]
    public function it_leaves_the_rules_and_the_meta_alone(): void
    {
        $field = Text::make('email')
            ->label('email address')
            ->messages(['required' => 'A']);

        $this->assertSame(['string'], $field->getRules()->shared());
        $this->assertSame([], $field->getMeta());
    }
}
