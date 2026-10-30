<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Closure;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Fields\WriteRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WriteRulesTest extends TestCase
{
    #[Test]
    public function it_merges_the_field_operation_and_shared_rules_key_by_key_as_lists(): void
    {
        $fields = FieldSet::of([Text::make('title')->rules('max:60')->creationRules('required'), Integer::make('views')]);

        $rules = WriteRules::of(
            $fields,
            WriteOperation::Create,
            ['title' => 'alpha_dash', 'summary' => ['nullable', 'string']],
            ['title' => 'string|min:3', 'metadata.source' => 'string'],
        );

        $this->assertSame([
            'title' => ['string', 'max:60', 'required', 'alpha_dash', 'string', 'min:3'],
            'views' => ['integer'],
            'summary' => ['nullable', 'string'],
            'metadata.source' => ['string'],
        ], $rules->all());
    }

    #[Test]
    public function it_takes_the_rules_of_the_fields_writable_on_the_operation(): void
    {
        $fields = FieldSet::of([ID::make('id'), Text::make('slug')->updatable(false), Text::make('name')->updateRules('filled'), Text::make('initials')->virtual()]);

        $this->assertSame(['slug' => ['string'], 'name' => ['string']], WriteRules::of($fields, WriteOperation::Create)->all());
        $this->assertSame(['name' => ['string', 'filled']], WriteRules::of($fields, WriteOperation::Update)->all());
    }

    #[Test]
    public function it_keeps_rule_objects_in_the_merged_list(): void
    {
        $unique = Rule::unique('posts', 'title');

        $rules = WriteRules::of(FieldSet::of([Text::make('title')]), WriteOperation::Create, ['title' => $unique]);

        $this->assertSame(['title' => ['string', $unique]], $rules->all());
    }

    /** @param list<mixed> $expected */
    #[Test]
    #[DataProvider('declarations')]
    public function it_turns_a_rule_declaration_into_a_list(mixed $declaration, array $expected): void
    {
        $this->assertSame($expected, WriteRules::listOf($declaration));
    }

    /** @return iterable<string, array{mixed, list<mixed>}> */
    public static function declarations(): iterable
    {
        $closure = static function (string $attribute, mixed $value, Closure $fail): void {};

        yield 'a rule' => ['string', ['string']];
        yield 'a pipe string' => ['required|string|max:5', ['required', 'string', 'max:5']];
        yield 'a list' => [['required', 'max:5'], ['required', 'max:5']];
        yield 'a keyed array' => [['first' => 'required', 'second' => 'max:5'], ['required', 'max:5']];
        yield 'a closure' => [$closure, [$closure]];
    }

    #[Test]
    public function it_prefixes_the_keys_and_points_the_references_to_declared_fields_at_the_same_entry(): void
    {
        $fields = FieldSet::of([Date::make('published_on'), DateTime::make('published_at')->rules('after_or_equal:published_on')]);

        $rules = WriteRules::of($fields, WriteOperation::Create, ['summary' => 'required_if:featured,true'], ['published_at' => 'before:published_on']);

        $this->assertSame([
            'mutate.0.attributes.published_on' => ['date'],
            'mutate.0.attributes.published_at' => ['date', 'after_or_equal:mutate.0.attributes.published_on', 'before:mutate.0.attributes.published_on'],
            'mutate.0.attributes.summary' => ['required_if:featured,true'],
        ], $rules->under('mutate.0.attributes.'));
    }
}
