<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Stubs\ContainsPostController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\FullTextPostController;
use PHPUnit\Framework\Attributes\Test;

class ScrambleTextSearchTest extends ScrambleTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(function (): void {
            Rest::resource('contains-posts', ContainsPostController::class)->register();
            Rest::resource('full-text-posts', FullTextPostController::class)->register();
        });
    }

    #[Test]
    public function it_documents_the_text_and_the_modes_of_the_text_search_of_the_resource(): void
    {
        $search = $this->search('/contains-posts/search');

        $this->assertSame([
            'type' => 'object',
            'description' => 'Text to search in the searchable fields',
            'properties' => [
                'value' => ['type' => 'string'],
                'mode' => ['type' => 'string', 'enum' => ['words', 'phrase']],
            ],
        ], $search['text']);
        $this->assertNotContains('@relevance', $search['sorts']['items']['properties']['field']['enum']);
    }

    #[Test]
    public function it_documents_the_relevance_sort_of_a_text_search_that_ranks_its_matches(): void
    {
        $search = $this->search('/full-text-posts/search');
        $fullText = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
        $sorts = ['id', 'title', 'summary', 'status', 'featured', 'views', 'rating', 'published_on', 'published_at'];

        $this->assertSame(
            $fullText ? ['words', 'prefix', 'phrase', 'websearch'] : ['words', 'phrase'],
            $search['text']['properties']['mode']['enum'],
        );
        $this->assertSame($fullText ? [...$sorts, '@relevance'] : $sorts, $search['sorts']['items']['properties']['field']['enum']);
    }

    #[Test]
    public function it_documents_the_trashed_models_only_on_a_resource_that_soft_deletes(): void
    {
        $search = $this->search('/authors/search');

        $this->assertSame(['type' => 'string', 'enum' => ['with', 'only']], $search['trashed']);
        $this->assertArrayNotHasKey('text', $search);
    }

    /** @return array<string, mixed> */
    private function search(string $path): array
    {
        return $this->body($this->operation($path, 'post'))['properties']['search']['properties'];
    }
}
