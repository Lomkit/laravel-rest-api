<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InputFieldSetTest extends TestCase
{
    #[Test]
    public function it_accepts_input_fields_that_declare_shared_rules_only(): void
    {
        $set = FieldSet::forInputs(
            [Boolean::make('featured')->default(true), Integer::make('min_views')->nullable()->rules('min:1')],
            'HighlightPosts::fields()',
        );

        $this->assertSame(['featured', 'min_views'], $set->names());
    }

    /** @return iterable<string, array{Field}> */
    public static function fieldsWithWriteRules(): iterable
    {
        yield 'creation rules' => [Text::make('reason')->creationRules('required')];
        yield 'update rules' => [Text::make('reason')->updateRules('max:1')];
    }

    #[Test]
    #[DataProvider('fieldsWithWriteRules')]
    public function it_rejects_creation_or_update_rules_on_an_input_field(Field $field): void
    {
        $this->expectExceptionObject(InvalidFieldException::writeRulesOnInput('PublishPosts::fields()', 'reason'));
        $this->expectExceptionMessage('PublishPosts::fields() declares creation or update rules on the input field [reason]. Input fields take rules() only.');

        FieldSet::forInputs([Boolean::make('notify'), $field], 'PublishPosts::fields()');
    }

    #[Test]
    public function it_checks_the_entries_like_any_field_set_first(): void
    {
        $this->expectExceptionObject(InvalidFieldException::duplicateName('PublishPosts::fields()', 'notify'));

        FieldSet::forInputs([Boolean::make('notify'), Boolean::make('notify')->creationRules('required')], 'PublishPosts::fields()');
    }
}
