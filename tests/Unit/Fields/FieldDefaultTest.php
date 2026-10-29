<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldDefaultTest extends TestCase
{
    #[Test]
    public function it_has_no_default_until_one_is_declared(): void
    {
        $field = Integer::make('min_views');

        $this->assertFalse($field->hasDefault());
        $this->assertNull($field->defaultValue());
    }

    #[Test]
    public function it_returns_the_declared_default(): void
    {
        $field = Integer::make('min_views');

        $this->assertSame($field, $field->default(100));
        $this->assertTrue($field->hasDefault());
        $this->assertSame(100, $field->defaultValue());
    }

    #[Test]
    public function it_tells_a_null_default_from_no_default(): void
    {
        $field = Text::make('note')->nullable()->default(null);

        $this->assertTrue($field->hasDefault());
        $this->assertNull($field->defaultValue());
    }

    #[Test]
    public function it_keeps_the_last_declared_default(): void
    {
        $field = Boolean::make('featured')->default(true)->default(false);

        $this->assertTrue($field->hasDefault());
        $this->assertFalse($field->defaultValue());
    }
}
