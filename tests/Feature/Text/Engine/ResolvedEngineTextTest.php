<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\GrammarlessEngineSearch;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\GrammarlessVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\TextBackend;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ResolvedEngineTextTest extends EngineTestCase
{
    #[Test]
    public function it_resolves_a_text_on_the_engine_of_the_resource(): void
    {
        Ledger::seed();
        $context = $this->context(['search' => ['text' => ['value' => 'report alpha']]]);

        $text = $context->text($this->resource(VoucherResource::class, $context));

        $this->assertSame(TextBackend::Engine, $text->backend);
        $this->assertTrue($text->usesEngine());
        $this->assertSame($text->capabilities?->engine, $text->engineGrammar());
        $this->assertFalse($text->ranks());
        $this->assertNull($text->clause());
        $this->assertNull($text->relevance());
        $this->assertSame('ran', $text->run(fn (): string => 'ran'));
        $this->assertEquals(new EnginePage(['V-c'], 1), $text->engineQuery($context->request)?->page(10, 1));
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('searchesWithoutTerms')]
    public function it_leaves_a_search_without_terms_to_the_database(array $search): void
    {
        $context = $this->context(['search' => $search]);

        $text = $context->text($this->resource(VoucherResource::class, $context));

        $this->assertSame(TextBackend::Engine, $text->backend);
        $this->assertFalse($text->usesEngine());
        $this->assertNull($text->engineGrammar());
        $this->assertNull($text->engineQuery($context->request));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function searchesWithoutTerms(): array
    {
        return [
            'no text' => [[]],
            'a blank text' => [['text' => ['value' => ' ']]],
            'a text without letters or digits' => [['text' => ['value' => '%_!']]],
        ];
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('searchesOfAnyKind')]
    public function it_refuses_an_engine_text_search_that_states_no_engine_grammar(array $search): void
    {
        $context = $this->context(['search' => $search]);

        $this->expectExceptionObject(InvalidTextSearchException::missingEngineGrammar(GrammarlessVoucherResource::class, GrammarlessEngineSearch::class));

        $context->text($this->resource(GrammarlessVoucherResource::class, $context));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function searchesOfAnyKind(): array
    {
        return [
            'a text with a scope' => [['text' => ['value' => 'report'], 'scopes' => [['name' => 'cheap']]]],
            'no text' => [[]],
        ];
    }
}
