<?php

namespace Lomkit\Rest\Tests\Feature\Search\Plan;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Search\Plan\Columns\BelongsToKeys;
use Lomkit\Rest\Search\Plan\Columns\BelongsToManyKeys;
use Lomkit\Rest\Search\Plan\Columns\HasOneOrManyKeys;
use Lomkit\Rest\Search\Plan\Columns\HasOneOrManyThroughKeys;
use Lomkit\Rest\Search\Plan\Columns\MorphOneOrManyKeys;
use Lomkit\Rest\Search\Plan\Columns\MorphToKeys;
use Lomkit\Rest\Search\Plan\Columns\RelationKeys;
use Lomkit\Rest\Search\Plan\Columns\UnknownRelationKeys;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ShelvedLabel;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RelationKeysTest extends PackageTestCase
{
    /**
     * @param  class-string<Model>  $model
     * @param  class-string<RelationKeys>  $strategy
     * @param  list<string>  $parent
     * @param  list<string>  $related
     */
    #[Test]
    #[DataProvider('relations')]
    public function it_reads_the_keys_of_each_side_of_a_relation(string $model, string $relation, string $strategy, array $parent, array $related): void
    {
        $keys = RelationKeys::between(new $model, $relation);

        $this->assertInstanceOf($strategy, $keys);
        $this->assertSame([$parent, $related], [$keys->parent(), $keys->related()]);
    }

    /** @return array<string, array{class-string<Model>, string, class-string<RelationKeys>, list<string>, list<string>}> */
    public static function relations(): array
    {
        return [
            'a belongs to' => [Loan::class, 'book', BelongsToKeys::class, ['book_id'], ['id']],
            'a morph to' => [Label::class, 'labelable', MorphToKeys::class, ['labelable_type', 'labelable_id'], []],
            'a morph to with an owner key' => [ShelvedLabel::class, 'shelf', MorphToKeys::class, ['labelable_type', 'labelable_id'], ['code']],
            'a has one' => [Book::class, 'loan', HasOneOrManyKeys::class, ['id'], ['book_id']],
            'a has one of many' => [Book::class, 'latestLoan', HasOneOrManyKeys::class, ['id'], ['book_id']],
            'a has many' => [Team::class, 'tickets', HasOneOrManyKeys::class, ['id'], ['team_id']],
            'a morph many' => [Book::class, 'labels', MorphOneOrManyKeys::class, ['id'], ['labelable_id', 'labelable_type']],
            'a belongs to many' => [Book::class, 'shelves', BelongsToManyKeys::class, ['id'], []],
            'a morph to many' => [Ticket::class, 'labels', BelongsToManyKeys::class, ['id'], []],
            'a has many through' => [Team::class, 'replies', HasOneOrManyThroughKeys::class, ['id'], []],
            'a relation of another class' => [ShelvedLabel::class, 'siblings', UnknownRelationKeys::class, ['*'], ['*']],
        ];
    }
}
