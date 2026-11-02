<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\Rejected;
use Lomkit\Rest\Validation\Violation;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $parsed->reject(Violation::unknown('search.flag'));

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

    #[Test]
    public function it_gives_the_filled_rule_to_a_blank_string_after_the_other_rules_of_its_key(): void
    {
        $parsed = new ParsedRules;
        $parsed->add('search.limit', ['integer']);
        $parsed->needsValue('search.limit', '  ');
        $parsed->add('search.limit', ['min:1']);

        $this->assertSame(['search.limit' => ['integer', 'min:1', 'filled']], $parsed->rules());
    }

    #[Test]
    public function it_gives_the_filled_rule_after_the_rules_added_once_the_blank_is_read(): void
    {
        $parsed = new ParsedRules;
        $parsed->add('search', ['array']);
        $parsed->needsValue('search.trashed', '');
        $parsed->add('search.limit', ['integer']);
        $parsed->add('search.trashed', ['prohibited']);
        $parsed->reject(Violation::unknown('search.trashed'));

        $rules = $parsed->rules();

        $this->assertSame(['search', 'search.trashed', 'search.limit'], array_keys($rules));
        $this->assertInstanceOf(Rejected::class, $rules['search.trashed'][0]);
        $this->assertSame(['prohibited', 'filled'], array_slice($rules['search.trashed'], 1));
    }

    #[Test]
    public function it_gives_no_rule_to_a_value_that_is_not_a_blank_string(): void
    {
        $parsed = new ParsedRules;

        foreach ([null, 'a', ' a ', 0, false, []] as $index => $value) {
            $parsed->needsValue("key{$index}", $value);
        }

        $this->assertSame([], $parsed->rules());
    }

    #[Test]
    public function it_gives_the_filled_rule_once(): void
    {
        $parsed = new ParsedRules;
        $parsed->add('min', ['required', 'filled', 'integer']);
        $parsed->needsValue('min', '');

        $this->assertSame(['min' => ['required', 'filled', 'integer']], $parsed->rules());
    }

    #[Test]
    public function it_gives_the_filled_rule_to_a_blank_value_whose_rules_cannot_take_it(): void
    {
        $parsed = new ParsedRules;
        $parsed->needsValue('min', '', ['required', 'integer']);
        $parsed->needsValue('tags', '', ['present', 'array', 'list']);

        $this->assertSame(['min' => ['filled'], 'tags' => ['filled']], $parsed->rules());
    }

    /** @param list<mixed> $rules */
    #[Test]
    #[DataProvider('rulesTakingBlanks')]
    public function it_leaves_a_blank_value_to_rules_that_take_it(array $rules): void
    {
        $parsed = new ParsedRules;
        $parsed->needsValue('value', '', $rules);

        $this->assertSame([], $parsed->rules());
    }

    /** @return array<string, array{list<mixed>}> */
    public static function rulesTakingBlanks(): array
    {
        return [
            'a string' => [['required', 'string', 'max:60']],
            'a nullable value' => [['nullable', 'integer']],
            'a prohibited value' => [['prohibited']],
        ];
    }
}
