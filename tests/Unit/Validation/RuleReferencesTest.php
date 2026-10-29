<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Illuminate\Validation\ConditionalRules;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Validation\RuleReferences;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RuleReferencesTest extends TestCase
{
    private const PATHS = [
        'status' => 'fields.status',
        'min' => 'fields.min',
        'max' => 'fields.max',
    ];

    /** @return iterable<string, array{mixed, mixed}> */
    public static function references(): iterable
    {
        yield 'first parameter of a conditional rule' => ['required_if:status,draft', 'required_if:fields.status,draft'];
        yield 'comparison with a sibling' => ['gte:min', 'gte:fields.min'];
        yield 'date compared with a sibling' => ['after:min', 'after:fields.min'];
        yield 'every parameter of a presence rule' => ['required_with:min,max', 'required_with:fields.min,fields.max'];
        yield 'every parameter of prohibits' => ['prohibits:min,max', 'prohibits:fields.min,fields.max'];
        yield 'every parameter of different' => ['different:min,max', 'different:fields.min,fields.max'];
        yield 'every parameter of exclude_without' => ['exclude_without:min,max', 'exclude_without:fields.min,fields.max'];
        yield 'first parameter of exclude_with' => ['exclude_with:min,max', 'exclude_with:fields.min,max'];
        yield 'values of a conditional rule' => ['required_if:status,status', 'required_if:fields.status,status'];
        yield 'array form' => [['required_if', 'status', 'draft'], ['required_if', 'fields.status', 'draft']];
        yield 'quoted values' => ['required_if:status,"draft,review"', ['required_if', 'fields.status', 'draft,review']];
        yield 'date equal to a sibling' => ['date_equals:min', 'date_equals:fields.min'];
        yield 'values of a sibling list' => ['in_array:status.*', 'in_array:fields.status.*'];
    }

    #[Test]
    #[DataProvider('references')]
    public function it_points_dependent_parameters_at_the_declared_siblings(mixed $rule, mixed $expected): void
    {
        $this->assertSame([$expected], RuleReferences::rewrite([$rule], self::PATHS));
    }

    #[Test]
    public function it_points_the_parameters_of_rule_objects_at_the_declared_siblings(): void
    {
        $this->assertSame(
            ['date', 'after:fields.min', 'numeric', 'gte:fields.max', 'string', 'required'],
            RuleReferences::rewrite([Rule::date()->after('min'), Rule::numeric()->greaterThanOrEqualTo('max'), Rule::string(), 'required'], self::PATHS),
        );
    }

    #[Test]
    public function it_points_conditional_rules_at_the_declared_siblings(): void
    {
        [$conditional] = RuleReferences::rewrite([Rule::when(fn (): bool => true, ['required_with:min'], ['required_without:max'])], self::PATHS);

        $this->assertInstanceOf(ConditionalRules::class, $conditional);
        $this->assertTrue($conditional->passes([]));
        $this->assertSame(['required_with:fields.min'], $conditional->rules([]));
        $this->assertSame(['required_without:fields.max'], $conditional->defaultRules([]));
    }

    /** @return iterable<string, array{mixed}> */
    public static function untouched(): iterable
    {
        yield 'rule that does not depend on other fields' => ['in:status,min'];
        yield 'size rule' => ['max:10'];
        yield 'date that is not a sibling' => ['after:tomorrow'];
        yield 'number that is not a sibling' => ['gt:10'];
        yield 'unique table and column' => ['unique:status,min'];
        yield 'rule without parameters' => ['required'];
        yield 'rule object' => [Rule::in(['status'])];
    }

    #[Test]
    #[DataProvider('untouched')]
    public function it_leaves_other_rules_and_parameters_as_they_are(mixed $rule): void
    {
        $this->assertSame([$rule], RuleReferences::rewrite([$rule], self::PATHS));
    }

    #[Test]
    public function it_keeps_the_rules_when_no_sibling_is_declared(): void
    {
        $this->assertSame(['required_if:status,draft'], RuleReferences::rewrite(['required_if:status,draft'], []));
    }

    #[Test]
    public function it_fills_the_wildcards_of_dependent_parameters_with_the_matched_values(): void
    {
        $this->assertSame(
            ['required_with:items.2.tags.0.name,items.2.code', 'gte:items.2.min', 'in:*'],
            RuleReferences::substituteWildcards(['required_with:items.*.tags.*.name,items.*.code', 'gte:items.*.min', 'in:*'], ['2', '0']),
        );
    }

    #[Test]
    public function it_keeps_the_wildcards_without_matched_values(): void
    {
        $this->assertSame(['gte:items.*.min'], RuleReferences::substituteWildcards(['gte:items.*.min'], []));
    }

    #[Test]
    public function it_fills_the_wildcards_of_rule_objects_and_leaves_other_rules_as_laravel_does(): void
    {
        $this->assertSame(
            ['date', 'after:items.2.start', 'in_array:items.*.code'],
            RuleReferences::substituteWildcards([Rule::date()->after('items.*.start'), 'in_array:items.*.code'], ['2']),
        );
    }
}
