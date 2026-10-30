<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Validation\Validator;
use Lomkit\Rest\Validation\RuleCompiler;
use Lomkit\Rest\Validation\WildcardKeys;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WildcardKeysTest extends TestCase
{
    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function payloads(): iterable
    {
        yield 'list entries' => [['items' => [['name' => 'Lamp'], ['name' => 'Desk']]], 'items.*'];
        yield 'named segment after a wildcard' => [['items' => [['name' => 'Lamp'], ['name' => 'Desk']]], 'items.*.name'];
        yield 'missing named segment' => [['items' => [['name' => 'Lamp'], ['price' => 3]]], 'items.*.name'];
        yield 'missing named path' => [['items' => [['meta' => ['label' => 'New']], []]], 'items.*.meta.label'];
        yield 'nested wildcards' => [['items' => [['tags' => ['red', 'blue']], ['tags' => ['green']]]], 'items.*.tags.*'];
        yield 'missing nested list' => [['items' => [['tags' => ['red']], ['name' => 'Desk']]], 'items.*.tags.*'];
        yield 'string keys' => [['groups' => ['first' => ['size' => 1], 'second' => ['size' => 2]]], 'groups.*.size'];
        yield 'leading named segments' => [['search' => ['filters' => [['field' => 'name'], ['field' => 'price']]]], 'search.filters.*.field'];
        yield 'empty entry' => [['items' => [[], ['name' => 'Desk']]], 'items.*'];
        yield 'scalar entry before a named segment' => [['items' => ['Lamp', ['name' => 'Desk']]], 'items.*.name'];
        yield 'scalar under a wildcard' => [['items' => 'Lamp'], 'items.*'];
        yield 'absent list' => [['name' => 'Lamp'], 'items.*'];
    }

    /** @param array<string, mixed> $data */
    #[Test]
    #[DataProvider('payloads')]
    public function it_expands_the_same_keys_as_the_laravel_rule_parser(array $data, string $key): void
    {
        $laravel = array_keys((new ValidationRuleParser($data))->explode([$key => ['string']])->rules);
        $expanded = array_column(WildcardKeys::expand($data, $key), 0);

        sort($laravel);
        sort($expanded);

        $this->assertSame($laravel, $expanded);
    }

    #[Test]
    public function it_returns_the_values_each_wildcard_matched(): void
    {
        $data = ['items' => [['tags' => ['red', 'blue']], ['tags' => ['green']]]];

        $this->assertSame(
            [['items.0.tags.0', ['0', '0']], ['items.0.tags.1', ['0', '1']], ['items.1.tags.0', ['1', '0']]],
            WildcardKeys::expand($data, 'items.*.tags.*'),
        );
    }

    /** @return iterable<string, array{array<string, mixed>, array<string, mixed>}> */
    public static function validations(): iterable
    {
        yield 'parameters with a wildcard' => [
            ['items.*.end' => ['integer', 'gte:items.*.start']],
            ['items' => [['start' => 1, 'end' => 3], ['start' => 5, 'end' => 2]]],
        ];
        yield 'parameters with nested wildcards' => [
            ['items.*.tags.*' => ['string', 'different:items.*.name']],
            ['items' => [['name' => 'red', 'tags' => ['red', 'blue']], ['name' => 'blue', 'tags' => ['blue']]]],
        ];
        yield 'distinct values' => [
            ['tags.*' => ['string', 'distinct']],
            ['tags' => ['red', 'blue', 'red']],
        ];
        yield 'distinct values under a named segment' => [
            ['items.*.name' => ['required', 'distinct']],
            ['items' => [['name' => 'Lamp'], ['name' => 'Lamp'], ['price' => 3]]],
        ];
        yield 'rules after distinct' => [
            ['items.*.name' => ['string', 'distinct', 'in:Lamp,Desk']],
            ['items' => [['name' => 'Chair'], ['name' => 'Chair'], ['name' => 'Lamp']]],
        ];
        yield 'distinct first' => [
            ['tags.*' => ['distinct', 'integer']],
            ['tags' => ['red', 'red', 3]],
        ];
        yield 'sometimes' => [
            ['items.*.price' => ['sometimes', 'integer']],
            ['items' => [['price' => 'free'], ['name' => 'Desk']]],
        ];
        yield 'missing named path' => [
            ['items.*.meta.label' => ['required']],
            ['items' => [['meta' => []], ['meta' => ['label' => 'New']]]],
        ];
        yield 'camel case keys' => [
            ['lineItems.*.unitPrice' => ['required']],
            ['lineItems' => [['quantity' => 2]]],
        ];
        yield 'keys holding a dot' => [
            ['items.*' => ['array'], 'items.*.price' => ['integer']],
            ['items' => ['desk.lamp' => ['price' => 'free'], 'chair' => 'none']],
        ];
        yield 'distinct before another wildcard key' => [
            ['tags.*' => ['distinct', 'integer'], 'names.*' => ['required', 'string']],
            ['tags' => ['red', 'red'], 'names' => [null]],
        ];
        yield 'explicit rule next to a sometimes wildcard' => [
            ['items.0.price' => ['required'], 'items.*.price' => ['sometimes', 'integer']],
            ['items' => [[], ['price' => 'free']]],
        ];
        yield 'explicit sometimes next to a wildcard rule' => [
            ['items.0.price' => ['sometimes', 'integer'], 'items.*.price' => ['required']],
            ['items' => [[], []]],
        ];
        yield 'date rule object with a wildcard parameter' => [
            ['items.*.end' => [Rule::date()->after('items.*.start')]],
            ['items' => [['start' => '2024-01-10', 'end' => '2024-01-01'], ['start' => '2024-01-01', 'end' => '2024-01-10']]],
        ];
        yield 'numeric rule object with a wildcard parameter' => [
            ['items.*.max' => [Rule::numeric()->greaterThanOrEqualTo('items.*.min')]],
            ['items' => [['min' => 5, 'max' => 2], ['min' => 1, 'max' => 2]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $data
     */
    #[Test]
    #[DataProvider('validations')]
    public function it_reports_the_same_errors_as_laravel_once_compiled(array $rules, array $data): void
    {
        $compiled = RuleCompiler::compile($rules, $data);
        $laravel = new Validator($this->translator(), $data, $rules);
        $explicit = (new Validator($this->translator(), $compiled->data($data), $compiled->rules))->setAttributeNames($compiled->labels);

        $this->assertNotSame([], $laravel->errors()->messages());
        $this->assertSame($laravel->errors()->messages(), $explicit->errors()->messages());
    }

    private function translator(): Translator
    {
        $loader = new ArrayLoader;
        $loader->addMessages('en', 'validation', [
            'required' => 'The :attribute field is required.',
            'integer' => 'The :attribute field must be an integer.',
            'string' => 'The :attribute field must be a string.',
            'distinct' => 'The :attribute field has a duplicate value.',
            'in' => 'The selected :attribute is invalid.',
            'different' => 'The :attribute field and :other must be different.',
            'gte' => ['numeric' => 'The :attribute field must be greater than or equal to :value.'],
            'array' => 'The :attribute field must be an array.',
            'date' => 'The :attribute field must be a valid date.',
            'after' => 'The :attribute field must be a date after :date.',
            'numeric' => 'The :attribute field must be a number.',
        ]);

        return new Translator($loader, 'en');
    }
}
