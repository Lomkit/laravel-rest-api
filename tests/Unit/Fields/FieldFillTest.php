<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldFillTest extends TestCase
{
    #[Test]
    public function it_fills_its_column_without_a_fill_callback(): void
    {
        $author = new Author;
        $field = Json::make('settings');

        $field->fill($author, ['theme' => 'dark']);

        $this->assertFalse($field->fillsThroughCallback());
        $this->assertSame(['theme' => 'dark'], $author->settings);
    }

    #[Test]
    public function it_hands_the_model_and_the_value_to_its_fill_callback(): void
    {
        $author = new Author;
        $field = Text::make('full_name')->virtual()->fillUsing(function (Model $model, mixed $value): void {
            $model->forceFill(['first_name' => 'Grace', 'last_name' => $value]);
        });

        $field->fill($author, 'Hopper');

        $this->assertTrue($field->fillsThroughCallback());
        $this->assertSame(['first_name' => 'Grace', 'last_name' => 'Hopper'], $author->getAttributes());
    }
}
