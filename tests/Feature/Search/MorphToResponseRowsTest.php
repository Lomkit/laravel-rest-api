<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class MorphToResponseRowsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[Test]
    public function it_counts_the_type_with_the_most_rows(): void
    {
        config(['rest.limits.sizes.rows' => 1209]);
        $key = 'search.includes.0.includes.0.types.'.Book::class.'.includes.0.relation';
        $message = "The {$key} field takes the response over the limit of 1209 rows: it can render up to 1210 rows.";

        $response = $this->postJson('/api/books/search', ['search' => ['limit' => 10, 'includes' => [
            ['relation' => 'labels', 'includes' => [['relation' => 'labelable', 'types' => [
                Shelf::class => [],
                Book::class => ['includes' => [['relation' => 'shelves']]],
            ]]]],
        ]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
    }

    #[Test]
    public function it_runs_a_search_whose_heaviest_type_stays_at_the_limit(): void
    {
        config(['rest.limits.sizes.rows' => 1210]);
        Book::query()->create(['id' => 1, 'title' => 'Emma']);

        $response = $this->postJson('/api/books/search', ['search' => ['limit' => 10, 'includes' => [
            ['relation' => 'labels', 'includes' => [['relation' => 'labelable', 'types' => [
                Shelf::class => [],
                Book::class => ['includes' => [['relation' => 'shelves']]],
            ]]]],
        ]]]);

        $response->assertOk()->assertJsonPath('data.0.id', 1);
    }
}
