<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FullTextSearchTest extends FullTextTestCase
{
    /** @var array<string, Post> */
    private array $posts = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'lamp' => ['Lamp', 'Lamp with lamp oil and a lamp shade', 5, false],
            'first desk' => ['Desk lamp', 'Brass', 5, true],
            'second desk' => ['Desk lamp', 'Brass', 1, false],
            'vase' => ['Glass vase', 'Hand blown glass', 300, false],
        ] as $key => [$title, $summary, $views, $featured]) {
            $this->posts[$key] = $this->createPost($title, $summary, ['views' => $views, 'featured' => $featured]);
        }
    }

    #[Test]
    public function it_orders_the_matches_by_relevance_then_by_the_default_order(): void
    {
        $this->requiresFullText();

        $response = $this->search('full-text-posts', ['text' => ['value' => 'lamp']]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->ids(['lamp', 'second desk', 'first desk']));
    }

    /** @param list<array<string, string>> $sorts */
    #[Test]
    #[DataProvider('relevanceSorts')]
    public function it_sorts_by_relevance_where_the_sorts_place_it(array $sorts, array $matches): void
    {
        $this->requiresFullText();

        $response = $this->search('full-text-posts', ['text' => ['value' => 'lamp'], 'sorts' => $sorts]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->ids($matches));
    }

    /** @return array<string, array{list<array<string, string>>, list<string>}> */
    public static function relevanceSorts(): array
    {
        return [
            'after a field sort' => [[['field' => 'views', 'direction' => 'asc'], ['field' => '@relevance']], ['second desk', 'lamp', 'first desk']],
            'before a field sort' => [[['field' => '@relevance', 'direction' => 'desc'], ['field' => 'views']], ['lamp', 'second desk', 'first desk']],
            'ascending' => [[['field' => '@relevance', 'direction' => 'asc'], ['field' => 'id', 'direction' => 'desc']], ['second desk', 'first desk', 'lamp']],
            'ascending after a field sort' => [[['field' => 'views', 'direction' => 'asc'], ['field' => '@relevance', 'direction' => 'asc']], ['second desk', 'first desk', 'lamp']],
        ];
    }

    /**
     * @param  array<string, string>  $text
     * @param  list<string>  $matches
     */
    #[Test]
    #[DataProvider('modes')]
    public function it_searches_the_full_text_index_in_each_mode(array $text, array $matches): void
    {
        $this->requiresFullText();

        $response = $this->search('full-text-posts', ['text' => $text, 'sorts' => [['field' => 'id']]]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->ids($matches));
    }

    /** @return array<string, array{array<string, string>, list<string>}> */
    public static function modes(): array
    {
        return [
            'words' => [['value' => 'glass'], ['vase']],
            'words in both fields' => [['value' => 'desk brass', 'mode' => 'words'], ['first desk', 'second desk']],
            'a phrase' => [['value' => 'lamp shade', 'mode' => 'phrase'], ['lamp']],
            'a phrase in another order' => [['value' => 'shade lamp', 'mode' => 'phrase'], []],
            'a prefix' => [['value' => 'lam', 'mode' => 'prefix'], ['lamp', 'first desk', 'second desk']],
            'prefixes' => [['value' => 'desk la', 'mode' => 'prefix'], ['first desk', 'second desk']],
            'an excluded word' => [['value' => 'lamp -brass', 'mode' => 'websearch'], ['lamp']],
            'alternatives' => [['value' => '"desk lamp" or vase', 'mode' => 'websearch'], ['first desk', 'second desk', 'vase']],
            'operators of the databases' => [['value' => '+lamp* | (shade) & !oil:*', 'mode' => 'prefix'], ['lamp']],
            'words joined in a web search' => [['value' => 'lamp brass', 'mode' => 'websearch'], ['first desk', 'second desk']],
            'a phrase with quotes' => [['value' => 'lamp" "shade', 'mode' => 'phrase'], ['lamp']],
            'a quoted or in a web search' => [['value' => 'lamp "or" vase', 'mode' => 'websearch'], []],
            'a hyphenated word in a web search' => [['value' => 'lamp-shade', 'mode' => 'websearch'], ['lamp']],
        ];
    }

    #[Test]
    public function it_falls_back_on_the_other_strategy_on_a_database_without_full_text(): void
    {
        if ($this->runsFullText()) {
            $this->markTestSkipped('The database runs full-text searches.');
        }

        $words = $this->search('full-text-posts', ['text' => ['value' => 'lamp']]);
        $prefix = $this->search('full-text-posts', ['text' => ['value' => 'lam', 'mode' => 'prefix']]);
        $relevance = $this->search('full-text-posts', ['text' => ['value' => 'lamp'], 'sorts' => [['field' => '@relevance']]]);

        $words->assertOk()->assertJsonPath('data.*.id', $this->ids(['second desk', 'first desk', 'lamp']));
        $prefix->assertUnprocessable()->assertJsonPath('errors', ['search.text.mode' => ['The selected search.text.mode is invalid.']]);
        $relevance->assertUnprocessable()->assertJsonPath('errors', ['search.sorts.0.field' => ['The selected search.sorts.0.field is invalid.']]);
    }

    #[Test]
    public function it_keeps_every_index_group_inside_the_perimeter_of_the_resource(): void
    {
        $response = $this->search('perimeter-posts', ['text' => ['value' => 'lamp']]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->ids(['first desk']));
    }

    #[Test]
    public function it_names_the_index_group_that_has_no_full_text_index(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Only MySQL and MariaDB need a FULLTEXT index for each group.');
        }

        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::missingFullTextIndex('posts', [['summary']], new QueryException('mysql', '', [], new PDOException)));

        $this->search('unindexed-full-text-posts', ['text' => ['value' => 'lamp']]);
    }

    #[Test]
    public function it_reads_the_words_with_the_language_of_the_search(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Only PostgreSQL reads the words with a text search configuration.');
        }

        $english = $this->search('full-text-posts', ['text' => ['value' => 'shades']]);
        $simple = $this->search('simple-full-text-posts', ['text' => ['value' => 'shades']]);
        $exact = $this->search('simple-full-text-posts', ['text' => ['value' => 'shade']]);

        $english->assertOk()->assertJsonPath('data.*.id', $this->ids(['lamp']));
        $simple->assertOk()->assertJsonPath('data', []);
        $exact->assertOk()->assertJsonPath('data.*.id', $this->ids(['lamp']));
    }

    /**
     * @param  list<string>  $posts
     * @return list<int>
     */
    private function ids(array $posts): array
    {
        return array_map(fn (string $post): int => $this->posts[$post]->id, $posts);
    }
}
