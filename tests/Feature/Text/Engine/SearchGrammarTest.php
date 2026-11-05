<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EngineGrammar;
use Lomkit\Rest\Text\Engine\EngineSearchGrammar;
use Lomkit\Rest\Text\TextBackend;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextMode;
use Lomkit\Rest\Validation\NodeBudget;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\Search\SqlSearchGrammar;
use Lomkit\Rest\Validation\ValidationContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class SearchGrammarTest extends EngineTestCase
{
    private const array TEXT = ['text' => ['value' => 'report']];

    #[Test]
    public function it_chooses_the_engine_grammar_for_a_text_with_terms(): void
    {
        $grammar = $this->validationContext(self::TEXT)->grammar;

        $this->assertInstanceOf(EngineSearchGrammar::class, $grammar);
        $this->assertSame(TextBackend::Engine, $grammar->backend());
    }

    #[Test]
    public function it_chooses_the_sql_grammar_for_a_text_without_terms(): void
    {
        $this->assertInstanceOf(SqlSearchGrammar::class, $this->validationContext(['text' => ['value' => '%']])->grammar);
    }

    #[Test]
    public function it_chooses_the_sql_grammar_without_a_text(): void
    {
        $grammar = $this->validationContext([])->grammar;

        $this->assertInstanceOf(SqlSearchGrammar::class, $grammar);
        $this->assertSame(TextBackend::Query, $grammar->backend());
    }

    #[Test]
    public function it_gives_the_sql_grammar_to_nested_searches(): void
    {
        $this->assertInstanceOf(SqlSearchGrammar::class, $this->validationContext(self::TEXT)->grammar->nested());
    }

    #[Test]
    public function it_accepts_the_engine_order_only_as_the_sole_sort(): void
    {
        $context = $this->validationContext(self::TEXT);

        $this->assertSame(['price', 'number', '@relevance'], $context->grammar->sortFields($context, 1)->values());
        $this->assertSame(['price', 'number'], $context->grammar->sortFields($context, 2)->values());
    }

    #[Test]
    public function it_lists_the_instructions_each_grammar_runs(): void
    {
        $this->assertSame(['priced-up-to', 'numbered-from'], $this->instructionNames($this->validationContext(self::TEXT)));
        $this->assertSame(['priced-up-to', 'with-lines'], $this->instructionNames($this->validationContext([])));
    }

    #[Test]
    public function it_checks_the_filtered_attributes_against_a_one_of_rule(): void
    {
        $search = [...self::TEXT, 'filters' => [['field' => 'price']]];

        $this->assertSame(['number', 'price', 'active', 'code'], $this->filteredAttributes('/api/vouchers/search', $search));
        $this->assertSame(['number', 'price', 'active', 'code'], $this->filteredAttributes('/api/team-vouchers/search', $search));
    }

    #[Test]
    public function it_applies_the_limits_of_the_engine_profile(): void
    {
        $context = $this->validationContext([]);
        $text = new TextCapabilities([], [TextMode::Words], true, false);
        $bounded = (new EngineGrammar([], ['price'], 2, 100, 1000))->searchGrammar($text);
        $open = (new EngineGrammar([], ['price']))->searchGrammar($text);

        $this->assertSame([2, 20], [$bounded->sortLimit($context->limits), $open->sortLimit($context->limits)]);
        $this->assertSame([['max:100'], []], [$bounded->limitRules(), $open->limitRules()]);
        $this->assertSame([['max:40'], ['max:20'], []], [$bounded->pageRules(25, 50), $bounded->pageRules('many', 50), $open->pageRules(25, 50)]);
        $this->assertSame(['prohibited'], $bounded->scopeRules());
        $this->assertFalse($bounded->trashed($context->resource));
    }

    #[Test]
    public function it_applies_no_engine_limit_to_the_sql_grammar(): void
    {
        $context = $this->validationContext([]);

        $this->assertSame(20, $context->grammar->sortLimit($context->limits));
        $this->assertSame([[], [], []], [$context->grammar->limitRules(), $context->grammar->pageRules(25, 50), $context->grammar->scopeRules()]);
        $this->assertTrue($context->grammar->trashed($context->resource));
    }

    /** @param array<string, mixed> $search */
    private function validationContext(array $search): ValidationContext
    {
        $resource = $this->context(['search' => $search])->resource(VoucherResource::class);

        return ValidationContext::root($resource, new NodeBudget, new ParsedRules);
    }

    /**
     * @param  array<string, mixed>  $search
     * @return list<string>
     */
    private function filteredAttributes(string $uri, array $search): array
    {
        $request = SearchRequest::create($uri, 'POST', ['search' => $search])->setContainer($this->app);
        $route = $this->app['router']->getRoutes()->match($request);
        $route->getController();
        $request->setRouteResolver(fn () => $route);
        $field = $request->rules()['search.filters.0.field'];
        $oneOf = array_values(array_filter(is_array($field) ? $field : [], fn (mixed $rule): bool => $rule instanceof OneOf));

        $this->assertCount(1, $oneOf);

        return $oneOf[0]->values();
    }

    /** @return list<string> */
    private function instructionNames(ValidationContext $context): array
    {
        $resource = $context->resource;
        $declared = $resource->definition()->instructions();

        return array_values(array_map(fn (Instruction $instruction): string => $instruction->uriKey(), $context->grammar->instructions($declared)));
    }
}
