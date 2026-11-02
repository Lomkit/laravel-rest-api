<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\InputSet;
use Lomkit\Rest\Validation\Limits;
use Lomkit\Rest\Validation\NodeBudget;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\PayloadReader;
use Lomkit\Rest\Validation\Rejected;
use Lomkit\Rest\Validation\Search\SqlSearchGrammar;
use Lomkit\Rest\Validation\ValidationContext;
use Lomkit\Rest\Validation\Violation;
use Lomkit\Rest\Validation\Vocabulary;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class InputSetTest extends TestCase
{
    private const array ENTRIES = [
        ['name' => 'minViews', 'value' => 10],
        ['name' => 'status', 'value' => 'draft'],
        ['name' => 'status', 'value' => 'review'],
        ['name' => 'unknown', 'value' => 1],
        ['name' => 'publishedAfter'],
        'minViews',
    ];

    private const array INPUT_RULES = [
        'fields.minViews' => ['integer', 'required_with:fields.status'],
        'fields.status' => ['string', 'in:draft,review'],
        'fields.publishedAfter' => ['nullable', 'date', 'after:tomorrow'],
    ];

    private ParsedRules $rules;

    #[Test]
    public function it_projects_each_declared_value_to_its_name(): void
    {
        $values = $this->read(['fields' => self::ENTRIES]);

        $this->assertSame(['fields.minViews' => 10, 'fields.status' => 'review'], $this->rules->projections());
        $this->assertSame(['minViews' => 10, 'status' => 'review'], $values->toArray());
        $this->assertTrue($this->rules->isPartial());
    }

    #[Test]
    public function it_validates_each_input_on_its_name_after_the_entry_names(): void
    {
        $this->read(['fields' => [['name' => 'minViews', 'value' => 10]]]);

        $this->assertSame(['fields.0.name', ...array_keys(self::INPUT_RULES)], array_keys($this->rules->rules()));
        $this->assertSame(self::INPUT_RULES, array_slice($this->rules->rules(), 1));
    }

    #[Test]
    public function it_validates_each_input_on_its_name_before_the_entry_names(): void
    {
        $this->read(['fields' => [['name' => 'minViews', 'value' => 10]]], inputsFirst: true);

        $this->assertSame([...array_keys(self::INPUT_RULES), 'fields.0.name'], array_keys($this->rules->rules()));
        $this->assertSame(self::INPUT_RULES, array_slice($this->rules->rules(), 0, 3));
    }

    #[Test]
    public function it_reads_the_same_inputs_in_both_orders(): void
    {
        $namedValues = $this->read(['fields' => self::ENTRIES]);
        $named = $this->rules;
        $inputsFirstValues = $this->read(['fields' => self::ENTRIES], inputsFirst: true);
        $namedRules = $named->rules();
        $inputsFirstRules = $this->rules->rules();
        ksort($namedRules);
        ksort($inputsFirstRules);

        $this->assertSame($named->projections(), $this->rules->projections());
        $this->assertSame($named->book()->attributeNames([], []), $this->rules->book()->attributeNames([], []));
        $this->assertSame($namedValues->toArray(), $inputsFirstValues->toArray());
        $this->assertSame($named->isPartial(), $this->rules->isPartial());
        $this->assertEquals($namedRules, $inputsFirstRules);
    }

    #[Test]
    public function it_labels_each_input_with_its_name_or_its_translation(): void
    {
        Lang::addLines(['validation.attributes.status' => 'publication status'], 'en');

        $this->read(['fields' => []]);

        $this->assertSame(
            ['fields.minViews' => 'min views', 'fields.status' => 'publication status', 'fields.publishedAfter' => 'published after'],
            $this->rules->book()->attributeNames([], []),
        );
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('emptyFields')]
    public function it_reads_missing_and_null_fields_as_an_empty_list(array $payload): void
    {
        $this->read($payload);

        $this->assertSame(array_keys(self::INPUT_RULES), array_keys($this->rules->rules()));
        $this->assertFalse($this->rules->isPartial());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function emptyFields(): array
    {
        return ['absent' => [[]], 'null' => [['fields' => null]]];
    }

    #[Test]
    public function it_reads_nothing_from_fields_that_are_not_an_array(): void
    {
        $this->read(['fields' => 'minViews']);

        $this->assertSame([], $this->rules->rules());
        $this->assertSame([], $this->rules->book()->attributeNames([], []));
        $this->assertTrue($this->rules->isPartial());
    }

    #[Test]
    public function it_requires_a_list_of_entries(): void
    {
        $this->read(['fields' => ['status' => ['name' => 'status', 'value' => 'draft']]]);

        $this->assertSame(['fields' => ['list']], $this->rules->rules());
        $this->assertSame([], $this->rules->projections());
        $this->assertTrue($this->rules->isPartial());
    }

    #[Test]
    public function it_rejects_an_unknown_entry_key(): void
    {
        $this->read(['fields' => [['name' => 'status', 'value' => 'draft', 'default' => 'x']]]);

        $this->assertEquals([new Rejected(Violation::unknown('fields.0.default'))], $this->rules->rules()['fields.0.default']);
    }

    #[Test]
    public function it_compares_with_the_siblings_in_rule_objects_and_conditional_rules(): void
    {
        $fields = FieldSet::forInputs([
            Date::make('since'),
            Date::make('until')->rules(Rule::date()->after('since')),
            Date::make('sameDay')->rules('date_equals:since'),
            Text::make('note')->rules(Rule::when(fn (): bool => true, ['required_with:since'])),
        ], 'ReportPosts::fields()');
        $payload = ['fields' => [
            ['name' => 'since', 'value' => '2024-01-10'],
            ['name' => 'until', 'value' => '2024-01-01'],
            ['name' => 'sameDay', 'value' => '2024-01-10'],
        ]];

        $this->read($payload, fields: $fields);
        $validator = Validator::make($this->rules->data($payload), $this->rules->rules(), [], $this->rules->book()->attributeNames([], []));

        $this->assertSame([
            'fields.until' => ['The until field must be a date after since.'],
            'fields.note' => ['The note field is required when since is present.'],
        ], $validator->errors()->messages());
    }

    /** @param array<string, mixed> $payload */
    private function read(array $payload, bool $inputsFirst = false, ?FieldSet $fields = null): InputValues
    {
        $rules = new ParsedRules;
        $this->rules = $rules;
        $limits = Limits::fromConfig((require __DIR__.'/../../../config/rest.php')['limits']);
        $context = new ValidationContext(new class extends Resource {}, new SqlSearchGrammar(null, false), $limits, $rules, new PayloadReader($rules, new NodeBudget), new Vocabulary);
        $cursor = Cursor::of($payload, $rules->violations())->at('fields');
        $inputs = new InputSet($fields ?? $this->fields());

        return $inputsFirst ? $inputs->inputsFirst($cursor, $context, 'action-fields:report') : $inputs->named($cursor, $context, 'action-fields:report');
    }

    private function fields(): FieldSet
    {
        return FieldSet::forInputs([
            Integer::make('minViews')->rules('required_with:status'),
            Text::make('status')->rules('in:draft,review'),
            Date::make('publishedAfter')
                ->nullable()
                ->rules('after:tomorrow'),
        ], 'HighlightPosts::fields()');
    }
}
