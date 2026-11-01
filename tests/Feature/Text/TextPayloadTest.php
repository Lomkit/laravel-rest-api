<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Tests\Feature\Search\Stubs\PayloadReadingSearchRequest;
use Lomkit\Rest\Text\TextMode;
use Lomkit\Rest\Text\TextQuery;
use PHPUnit\Framework\Attributes\Test;

class TextPayloadTest extends TextSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        PayloadReadingSearchRequest::$read = null;
        $this->app->bind(SearchRequest::class, PayloadReadingSearchRequest::class);
    }

    #[Test]
    public function it_reads_the_text_and_the_trashed_rows_of_a_search(): void
    {
        $this->search('contains-authors', ['text' => ['value' => 'ada', 'mode' => 'words'], 'trashed' => 'with'])->assertOk();

        $this->assertEquals(new TextQuery('ada', TextMode::Words), PayloadReadingSearchRequest::$read?->text);
        $this->assertSame(Trashed::With, PayloadReadingSearchRequest::$read?->trashed);
    }
}
