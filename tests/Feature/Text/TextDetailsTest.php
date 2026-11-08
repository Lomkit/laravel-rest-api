<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Stubs\UnsearchableCategoryController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\UnsearchableCategoryResource;
use PHPUnit\Framework\Attributes\Test;

class TextDetailsTest extends TextSearchTestCase
{
    private const string CONTAINS = '{"fields":["title","summary"],"modes":["words","phrase"],"relevance":false,"trashed":false,"max_length":255,"engine":null}';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('unsearchable-categories', UnsearchableCategoryController::class)->register();
    }

    #[Test]
    public function it_describes_a_contains_text_search(): void
    {
        $this->assertTextDetails(self::CONTAINS, $this->getJson('/contains-posts?with=text'));
    }

    #[Test]
    public function it_describes_a_full_text_search_or_the_search_it_falls_back_to(): void
    {
        $expected = $this->runsFullText()
            ? '{"fields":["title","summary"],"modes":["words","prefix","phrase","websearch"],"relevance":true,"trashed":false,"max_length":255,"engine":null}'
            : self::CONTAINS;

        $this->assertTextDetails($expected, $this->getJson('/full-text-posts?with=text'));
    }

    #[Test]
    public function it_reports_a_text_search_declaration_error_on_details(): void
    {
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::noSearchableField(UnsearchableCategoryResource::class));

        $this->getJson('/unsearchable-categories?with=text');
    }

    private function assertTextDetails(string $expected, TestResponse $response): void
    {
        $response->assertOk();

        $this->assertSame($expected, json_encode(json_decode((string) $response->getContent(), flags: JSON_THROW_ON_ERROR)->data->text));
    }
}
