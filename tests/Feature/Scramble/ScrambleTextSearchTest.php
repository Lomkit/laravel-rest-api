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
                'value' => ['type' => ['string', 'null'], 'maxLength' => 255],
                'mode' => ['type' => 'string', 'enum' => ['words', 'phrase']],
            ],
            'additionalProperties' => false,
        ], $search['text']);
        $this->assertSame(['$ref' => '#/components/schemas/ContainsPostSort'], $search['sorts']['items']);
        $this->assertNotContains('@relevance', $this->schemas()['ContainsPostSort']['properties']['field']['enum']);
    }

    #[Test]
    public function it_documents_the_relevance_sort_of_a_text_search_that_ranks_its_matches(): void
    {
        $search = $this->search('/full-text-posts/search');
        $fullText = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
        $sorts = ['id', 'title', 'summary', 'status', 'featured', 'views', 'rating', 'published_on', 'published_at', 'author.first_name', 'author.last_name', 'author_count', 'author_min_first_name', 'author_min_last_name', 'author_max_first_name', 'author_max_last_name', 'author_exists'];
        $sort = $fullText ? $search['sorts']['items'] : $this->schemas()['FullTextPostSort'];

        $this->assertSame(
            $fullText ? ['words', 'prefix', 'phrase', 'websearch'] : ['words', 'phrase'],
            $search['text']['properties']['mode']['enum'],
        );
        $this->assertSame($fullText ? [...$sorts, '@relevance'] : $sorts, $sort['properties']['field']['enum']);
    }

    #[Test]
    public function it_documents_the_trashed_models_only_on_a_resource_that_soft_deletes(): void
    {
        $search = $this->search('/authors/search');

        $this->assertSame(['type' => 'string', 'enum' => ['with', 'only']], $search['trashed']);
        $this->assertFalse($search['text']);
    }
}
