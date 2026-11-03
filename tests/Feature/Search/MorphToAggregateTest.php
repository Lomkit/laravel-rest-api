<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\Search\Stubs\UnlistedPolicy;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MorphToAggregateTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function morphToAggregates(): array
    {
        return [
            'a count' => ['/api/labels/search', ['aggregates' => [['relation' => 'labelable', 'type' => 'count']]], 'search.aggregates.0.relation'],
            'a maximum' => ['/api/labels/search', ['aggregates' => [['relation' => 'labelable', 'type' => 'max', 'field' => 'id']]], 'search.aggregates.0.relation'],
            'an existence' => ['/api/labels/search', ['aggregates' => [['relation' => 'labelable', 'type' => 'exists', 'alias' => 'labelled']]], 'search.aggregates.0.relation'],
            'an aggregate of an include' => ['/api/books/search', ['includes' => [['relation' => 'labels', 'aggregates' => [['relation' => 'labelable', 'type' => 'count']]]]], 'search.includes.0.aggregates.0.relation'],
        ];
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('morphToAggregates')]
    public function it_refuses_an_aggregate_on_a_morph_to_relation(string $uri, array $search, string $key): void
    {
        $message = "The {$key} field cannot be a MorphTo relation.";
        DB::enableQueryLog();

        $response = $this->postJson($uri, ['search' => $search]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_checks_the_target_before_refusing_the_aggregate(): void
    {
        Gate::policy(Book::class, UnlistedPolicy::class);

        $response = $this->postJson('/api/labels/search', ['search' => ['aggregates' => [['relation' => 'labelable', 'type' => 'count']]]]);

        $response->assertForbidden()->assertJsonPath('message', 'This list is closed.');
    }
}
