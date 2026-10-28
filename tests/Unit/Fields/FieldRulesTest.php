<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldRulesTest extends TestCase
{
    #[Test]
    public function it_orders_rules_as_nullable_base_rules_shared_rules_then_operation_rules(): void
    {
        $field = Text::make('title')
            ->updateRules('sometimes')
            ->creationRules('required')
            ->rules('max:255')
            ->nullable();

        $this->assertSame(['nullable', 'string', 'max:255', 'required'], $field->rulesFor(WriteOperation::Create));
        $this->assertSame(['nullable', 'string', 'max:255', 'sometimes'], $field->rulesFor(WriteOperation::Update));
    }

    /** @return iterable<string, array{Field, string}> */
    public static function fieldTypes(): iterable
    {
        yield 'id' => [ID::make('id'), 'integer'];
        yield 'text' => [Text::make('title'), 'string'];
        yield 'number' => [Number::make('rating'), 'numeric'];
        yield 'integer' => [Integer::make('views'), 'integer'];
        yield 'boolean' => [Boolean::make('featured'), 'boolean'];
        yield 'date' => [Date::make('published_on'), 'date'];
        yield 'datetime' => [DateTime::make('published_at'), 'date'];
        yield 'json' => [Json::make('metadata'), 'array'];
    }

    #[Test]
    #[DataProvider('fieldTypes')]
    public function it_puts_the_base_rule_of_each_type_between_nullable_and_the_declared_rules(Field $field, string $baseRule): void
    {
        $field->nullable()->rules('sometimes')->creationRules('required')->updateRules('filled');

        $this->assertSame(['nullable', $baseRule, 'sometimes', 'required'], $field->rulesFor(WriteOperation::Create));
        $this->assertSame(['nullable', $baseRule, 'sometimes', 'filled'], $field->rulesFor(WriteOperation::Update));
    }

    #[Test]
    public function it_drops_nullable_when_it_is_turned_off_again(): void
    {
        $field = Number::make('rating')->nullable()->nullable(false);

        $this->assertFalse($field->isNullable());
        $this->assertSame(['numeric'], $field->rulesFor(WriteOperation::Create));
    }

    #[Test]
    public function it_splits_pipe_separated_rules(): void
    {
        $field = Text::make('title')
            ->rules('required|max:255')
            ->creationRules('min:3|alpha_dash')
            ->updateRules('sometimes|filled');

        $this->assertSame([
            'all' => ['string', 'required', 'max:255'],
            'create' => ['min:3', 'alpha_dash'],
            'update' => ['sometimes', 'filled'],
        ], $field->ruleSets());
    }

    #[Test]
    public function it_keeps_array_form_rules_inside_a_rule_list(): void
    {
        $field = Text::make('title')
            ->rules(['required', ['max', 3]], 'distinct')
            ->creationRules(['unique:posts,title'])
            ->updateRules([['in', 'draft', 'review']]);

        $this->assertSame([
            'all' => ['string', 'required', ['max', 3], 'distinct'],
            'create' => ['unique:posts,title'],
            'update' => [['in', 'draft', 'review']],
        ], $field->ruleSets());
    }

    #[Test]
    public function it_keeps_a_pipe_inside_an_array_entry_like_the_laravel_validator(): void
    {
        $field = Text::make('status')->rules(['required', 'regex:/^(draft|review)$/']);

        $this->assertSame(['string', 'required', 'regex:/^(draft|review)$/'], $field->rulesFor(WriteOperation::Create));
    }

    #[Test]
    public function it_keeps_rule_objects_and_closures_as_they_are(): void
    {
        $in = Rule::in(['draft', 'review']);
        $custom = new class implements ValidationRule
        {
            public function validate(string $attribute, mixed $value, Closure $fail): void {}
        };
        $closure = function (string $attribute, mixed $value, Closure $fail): void {};

        $field = Text::make('status')->rules($in, ['max:20', $custom])->creationRules($closure);

        $this->assertSame(['string', $in, 'max:20', $custom, $closure], $field->rulesFor(WriteOperation::Create));
        $this->assertSame(['string', $in, 'max:20', $custom], $field->rulesFor(WriteOperation::Update));
    }

    #[Test]
    public function it_adds_up_repeated_rules_calls_in_call_order(): void
    {
        $field = Text::make('title')
            ->rules('required')
            ->creationRules('min:3')
            ->rules('max:255')
            ->updateRules('sometimes')
            ->creationRules('alpha_dash')
            ->updateRules('filled');

        $this->assertSame([
            'all' => ['string', 'required', 'max:255'],
            'create' => ['min:3', 'alpha_dash'],
            'update' => ['sometimes', 'filled'],
        ], $field->ruleSets());
    }

    #[Test]
    public function it_describes_a_nullable_number_with_shared_rules_only(): void
    {
        $this->assertSame(
            ['all' => ['nullable', 'numeric'], 'create' => [], 'update' => []],
            Number::make('price')->nullable()->ruleSets(),
        );
    }

    #[Test]
    public function it_applies_creation_rules_to_creation_only(): void
    {
        $field = Text::make('title')->rules('max:255')->creationRules('required');

        $this->assertSame(['all' => ['string', 'max:255'], 'create' => ['required'], 'update' => []], $field->ruleSets());
        $this->assertSame(['string', 'max:255', 'required'], $field->rulesFor(WriteOperation::Create));
        $this->assertSame(['string', 'max:255'], $field->rulesFor(WriteOperation::Update));
    }

    #[Test]
    public function it_places_the_enum_rule_object_after_nullable(): void
    {
        $rules = Enum::make('status', PostStatus::class)->nullable()->rules('required')->rulesFor(WriteOperation::Create);

        $this->assertEquals(['nullable', Rule::enum(PostStatus::class), 'required'], $rules);
    }

    #[Test]
    public function it_returns_the_same_rules_on_every_call(): void
    {
        $field = Text::make('title')->nullable()->rules('max:255')->creationRules('required');

        $this->assertSame($field->rulesFor(WriteOperation::Create), $field->rulesFor(WriteOperation::Create));
        $this->assertSame($field->ruleSets(), $field->ruleSets());
    }
}
