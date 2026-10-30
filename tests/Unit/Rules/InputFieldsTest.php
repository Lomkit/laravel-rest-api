<?php

namespace Lomkit\Rest\Tests\Unit\Rules;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Rules\Input\InputFields;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Validation\CompiledRules;
use Lomkit\Rest\Validation\RuleCompiler;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InputFieldsTest extends TestCase
{
    #[Test]
    public function it_projects_each_declared_value_to_its_name(): void
    {
        $compiled = $this->compile([
            ['name' => 'minViews', 'value' => 10],
            ['name' => 'status', 'value' => 'draft'],
            ['name' => 'status', 'value' => 'review'],
            ['name' => 'unknown', 'value' => 1],
            ['name' => 'publishedAfter'],
            'minViews',
        ]);

        $this->assertSame(['fields.minViews' => 10, 'fields.status' => 'review'], $compiled->projections);
    }

    #[Test]
    public function it_validates_each_input_on_its_name_with_sibling_references(): void
    {
        $compiled = $this->compile([['name' => 'minViews', 'value' => 10]]);

        $this->assertSame(
            [
                'fields.minViews' => ['integer', 'required_with:fields.status'],
                'fields.status' => ['string', 'in:draft,review'],
                'fields.publishedAfter' => ['nullable', 'date', 'after:tomorrow'],
            ],
            $compiled->rules,
        );
    }

    #[Test]
    public function it_labels_each_input_with_its_name_or_its_translation(): void
    {
        Lang::addLines(['validation.attributes.status' => 'publication status'], 'en');

        $this->assertSame(
            ['fields.minViews' => 'min views', 'fields.status' => 'publication status', 'fields.publishedAfter' => 'published after'],
            $this->compile([])->labels,
        );
    }

    #[Test]
    public function it_compiles_nothing_for_entries_that_are_not_a_list(): void
    {
        $instruction = new class($this->fields()) extends RestRule
        {
            public function __construct(private readonly FieldSet $fields) {}

            public function compile(RuleCompiler $into, string $attribute, mixed $value): void
            {
                (new InputFields($this->fields))->compile($into, $attribute.'.fields', $value['fields'] ?? []);
            }

            public function buildValidationRules(string $attribute, mixed $value): array
            {
                return [];
            }
        };

        $compiled = RuleCompiler::compile(['instruction' => [$instruction]], ['instruction' => ['fields' => 'minViews']]);

        $this->assertSame([], $compiled->rules);
        $this->assertSame([], $compiled->labels);
    }

    #[Test]
    public function it_requires_a_list_of_entries(): void
    {
        $compiled = $this->compile(['status' => ['name' => 'status', 'value' => 'draft']]);

        $this->assertSame(['fields' => ['list']], $compiled->rules);
        $this->assertSame([], $compiled->projections);
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

        $compiled = RuleCompiler::compile(['fields' => [new InputFields($fields)]], $payload);
        $validator = Validator::make($compiled->data($payload), $compiled->rules, [], $compiled->labels);

        $this->assertSame(
            [
                'fields.until' => ['The until field must be a date after since.'],
                'fields.note' => ['The note field is required when since is present.'],
            ],
            $validator->errors()->messages(),
        );
    }

    private function compile(mixed $entries): CompiledRules
    {
        return RuleCompiler::compile(['fields' => [new InputFields($this->fields())]], ['fields' => $entries]);
    }

    private function fields(): FieldSet
    {
        return FieldSet::forInputs([
            Integer::make('minViews')->rules('required_with:status'),
            Text::make('status')->rules('in:draft,review'),
            Date::make('publishedAfter')->nullable()->rules('after:tomorrow'),
        ], 'HighlightPosts::fields()');
    }
}
