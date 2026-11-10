<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class SearchQueriesTest extends PackageTestCase
{
    use AssertsExactErrors;
    use CapturesQueries;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_runs_no_statement_before_refusing_a_search(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/api/posts/search', ['search' => [
            'filters' => [...array_fill(0, 99, ['field' => 'views', 'value' => 1]), ['field' => 'secret']],
            'includes' => array_map(
                fn (string $alias): array => ['relation' => 'author', 'alias' => $alias, 'filters' => [['field' => 'first_name', 'value' => 'Ada']]],
                ['writer', 'editor', 'reviewer'],
            ),
            'scopes' => [['name' => 'popular']],
            'instructions' => [['name' => 'highlight-posts']],
            'sorts' => [['field' => 'views']],
        ]]);

        $this->assertExactErrors($response, ['search.filters.99.field' => ['The selected search.filters.99.field is invalid.']]);
        $this->assertSame([], $this->statements);
    }

    #[Test]
    public function it_runs_the_same_statements_for_one_filter_and_for_a_hundred(): void
    {
        Post::factory()
            ->count(3)
            ->create(['views' => 1]);

        $this->captureQueries();
        $one = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'views', 'value' => 1]]]]);
        $statements = count($this->statements);

        $this->statements = [];
        $hundred = $this->postJson('/api/posts/search', ['search' => ['filters' => array_fill(0, 100, ['field' => 'views', 'value' => 1])]]);

        $one->assertOk()->assertJsonCount(3, 'data');
        $hundred->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame($statements, count($this->statements));
    }
}
