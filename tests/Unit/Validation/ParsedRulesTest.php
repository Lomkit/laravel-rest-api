<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\Rejected;
use Lomkit\Rest\Validation\Violation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ParsedRulesTest extends TestCase
{
    #[Test]
    public function it_adds_the_rules_of_a_request_to_the_parsed_rules_of_a_key(): void
    {
        $parsed = new ParsedRules;
        $parsed->add('search.limit', ['integer']);

        $merged = $parsed->merge(['other' => 'required', 'search.limit' => 'integer|max:10']);

        $this->assertSame(['other' => 'required', 'search.limit' => ['integer', 'max:10']], $merged);
    }

    #[Test]
    public function it_keeps_the_parsed_rules_of_a_key_the_request_leaves_out(): void
    {
        $parsed = new ParsedRules;
        $parsed->add('search', ['array']);
        $parsed->add('search.limit', ['integer']);

        $this->assertSame(['other' => ['string'], 'search' => ['array'], 'search.limit' => ['integer']], $parsed->merge(['other' => ['string']]));
    }

    #[Test]
    public function it_keeps_the_rejections_of_the_parse_first(): void
    {
        $parsed = new ParsedRules;
        $parsed->add('search.flag', []);
        $parsed->reject(Violation::misplaced('search.flag'));

        $merged = $parsed->merge(['search.flag' => ['boolean']]);

        $this->assertIsArray($merged['search.flag']);
        $this->assertCount(2, $merged['search.flag']);
        $this->assertInstanceOf(Rejected::class, $merged['search.flag'][0]);
        $this->assertSame('boolean', $merged['search.flag'][1]);
        $this->assertSame($parsed->rules()['search.flag'][0], $merged['search.flag'][0]);
    }

    #[Test]
    public function it_adds_no_rule_the_parsed_list_already_holds(): void
    {
        $parsed = new ParsedRules;
        $parsed->add('search.sorts', ['array']);
        $rules = $parsed->rules();

        $this->assertSame($rules, $parsed->merge($rules));
    }
}
