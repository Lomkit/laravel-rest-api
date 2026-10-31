<?php

namespace Lomkit\Rest\Tests\Unit\Text;

use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Text\Engine\EngineGrammar;
use Lomkit\Rest\Text\Engine\IndexAttribute;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TextCapabilitiesTest extends TestCase
{
    #[Test]
    public function it_describes_a_text_search_on_the_database(): void
    {
        $capabilities = new TextCapabilities(['title', 'summary'], [TextMode::Words, TextMode::Phrase], false, true);

        $this->assertSame(
            '{"fields":["title","summary"],"modes":["words","phrase"],"relevance":false,"trashed":true,"max_length":255,"engine":null}',
            json_encode($capabilities),
        );
    }

    #[Test]
    public function it_describes_the_grammar_of_a_search_engine(): void
    {
        $grammar = new EngineGrammar(['price' => new IndexAttribute(Number::make('price'), [FilterOperator::Equal, FilterOperator::LessThanOrEqual])], ['price']);
        $capabilities = new TextCapabilities([], [TextMode::Words], true, false, engine: $grammar);

        $this->assertSame(
            '{"fields":[],"modes":["words"],"relevance":true,"trashed":false,"max_length":255,"engine":{"filters":[{"name":"price","type":"number","operators":["=","<="]}],"sorts":["price"],"max_sorts":null,"max_page_size":null,"window":null}}',
            json_encode($capabilities),
        );
    }
}
