<?php

namespace Lomkit\Rest\Tests\Unit\Search;

use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Search\AggregateKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AggregateKeyTest extends TestCase
{
    #[Test]
    #[DataProvider('aggregates')]
    public function it_derives_the_key_of_an_aggregate(AggregateFunction $function, ?Field $field, ?string $alias, string $key): void
    {
        $this->assertSame($key, AggregateKey::of('posts', $function, $field, $alias)->value);
    }

    /** @return array<string, array{AggregateFunction, ?Field, ?string, string}> */
    public static function aggregates(): array
    {
        return [
            'a field' => [AggregateFunction::Sum, Integer::make('views'), null, 'posts_sum_views'],
            'an alias' => [AggregateFunction::Max, ID::make('id'), 'last_post', 'last_post'],
            'an alias wins' => [AggregateFunction::Sum, Integer::make('views'), 'total_views', 'total_views'],
            'a function without a field' => [AggregateFunction::Exists, null, null, 'posts_exists'],
        ];
    }

    #[Test]
    #[DataProvider('paths')]
    public function it_derives_the_key_of_an_aggregate_on_a_relation_path(string $relation, AggregateFunction $function, ?Field $field, ?string $alias, string $key): void
    {
        $this->assertSame($key, AggregateKey::of($relation, $function, $field, $alias)->value);
    }

    /** @return array<string, array{string, AggregateFunction, ?Field, ?string, string}> */
    public static function paths(): array
    {
        return [
            'two relations' => ['tickets.tags', AggregateFunction::Count, null, null, 'tickets_tags_count'],
            'relations in camel case' => ['latestLoan.book', AggregateFunction::Max, ID::make('id'), null, 'latest_loan_book_max_id'],
            'three relations and a field' => ['tickets.tags.labelledTickets', AggregateFunction::Sum, Integer::make('team_id'), null, 'tickets_tags_labelled_tickets_sum_team_id'],
            'an alias wins' => ['tickets.tags', AggregateFunction::Count, null, 'tag_total', 'tag_total'],
        ];
    }
}
