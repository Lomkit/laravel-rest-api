<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Rules\Search\Search;
use Lomkit\Rest\Rules\Search\SearchFilter;
use Lomkit\Rest\Tests\Fixtures\Ledger\TeamVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineRuleListsTest extends EngineTestCase
{
    private const array TEXT = ['text' => ['value' => 'report']];

    #[Test]
    public function it_checks_the_filtered_attributes_of_an_engine_search_against_a_one_of_rule(): void
    {
        $voucher = $this->rule(VoucherResource::class, self::TEXT, 'search.filters.*')->buildValidationRules('filter', ['field' => 'price']);
        $team = $this->rule(TeamVoucherResource::class, self::TEXT, 'search.filters.*')->buildValidationRules('filter', ['field' => 'price']);

        $this->assertSame(['number', 'price', 'active', 'code'], $this->oneOf($voucher['filter.field']));
        $this->assertSame(['number', 'price', 'active', 'code'], $this->oneOf($team['filter.field']));
    }

    #[Test]
    public function it_checks_the_sorted_attributes_of_an_engine_search_against_a_one_of_rule(): void
    {
        $alone = $this->rule(VoucherResource::class, [...self::TEXT, 'sorts' => [['field' => 'price']]], 'search.sorts.*');
        $together = $this->rule(VoucherResource::class, [...self::TEXT, 'sorts' => [['field' => 'price'], ['field' => 'number']]], 'search.sorts.*');

        $this->assertSame(['price', 'number', '@relevance'], $this->oneOf($alone->buildValidationRules('sort', ['field' => 'price'])['sort.field']));
        $this->assertSame(['price', 'number'], $this->oneOf($together->buildValidationRules('sort', ['field' => 'price'])['sort.field']));
    }

    #[Test]
    public function it_checks_the_instruction_names_against_the_grammar_of_the_search(): void
    {
        $engine = $this->rule(VoucherResource::class, self::TEXT, 'search.instructions.*')->buildValidationRules('instruction', ['name' => 'with-lines']);
        $database = $this->rule(VoucherResource::class, [], 'search.instructions.*')->buildValidationRules('instruction', ['name' => 'with-lines']);

        $this->assertSame(['priced-up-to', 'numbered-from'], $this->oneOf($engine['instruction.name']));
        $this->assertSame(['priced-up-to', 'with-lines'], $this->oneOf($database['instruction.name']));
    }

    #[Test]
    public function it_checks_a_search_without_terms_against_the_database_grammar(): void
    {
        $this->assertInstanceOf(SearchFilter::class, $this->rule(VoucherResource::class, ['text' => ['value' => '%']], 'search.filters.*'));
    }

    /**
     * @param  class-string<Resource>  $class
     * @param  array<string, mixed>  $search
     */
    private function rule(string $class, array $search, string $key): RestRule
    {
        $resource = $this->context(['search' => $search])->resource($class);
        $rule = (new Search)->setResource($resource)->buildValidationRules('search', $search)[$key];

        $this->assertInstanceOf(RestRule::class, $rule);

        return $rule;
    }

    /**
     * @param  list<mixed>  $rules
     * @return list<string>
     */
    private function oneOf(array $rules): array
    {
        $oneOf = array_values(array_filter($rules, fn (mixed $rule): bool => $rule instanceof OneOf));

        $this->assertCount(1, $oneOf);

        return $oneOf[0]->values();
    }
}
