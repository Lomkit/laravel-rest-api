<?php

namespace Lomkit\Rest\Tests\Unit\Mutations;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Mutations\ChildWrites;
use Lomkit\Rest\Mutations\Mutator;
use Lomkit\Rest\Mutations\NoWrites;
use Lomkit\Rest\Mutations\OperationLog;
use Lomkit\Rest\Mutations\OwnerWrites;
use Lomkit\Rest\Mutations\PivotWrites;
use Lomkit\Rest\Mutations\RelationWrites;
use Lomkit\Rest\Mutations\RowSet;
use Lomkit\Rest\Relations\HasManyThrough;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ShelvedLabel;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\ReplyResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Desk;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RelationWritesTest extends PackageTestCase
{
    /** @param class-string<RelationWrites> $writes */
    #[Test]
    #[DataProvider('relations')]
    public function it_writes_each_relation_by_the_class_of_its_eloquent_relation(Model $parent, string $relation, string $writes): void
    {
        $this->assertInstanceOf($writes, RelationWrites::of($parent, Relation::make($relation, BookResource::class), []));
    }

    /** @return array<string, array{Model, string, class-string<RelationWrites>}> */
    public static function relations(): array
    {
        return [
            'a belongs to relation' => [new Loan, 'book', OwnerWrites::class],
            'a morph to relation' => [new Label, 'labelable', OwnerWrites::class],
            'a has one relation' => [new Book, 'loan', ChildWrites::class],
            'a has one of many relation' => [new Book, 'latestLoan', ChildWrites::class],
            'a has many relation' => [new Team, 'tickets', ChildWrites::class],
            'a morph one relation' => [new Book, 'label', ChildWrites::class],
            'a morph one of many relation' => [new Book, 'latestLabel', ChildWrites::class],
            'a morph many relation' => [new Book, 'labels', ChildWrites::class],
            'a belongs to many relation' => [new Book, 'shelves', PivotWrites::class],
            'a morph to many relation' => [new Ticket, 'labels', PivotWrites::class],
            'a morphed by many relation' => [new Tag, 'labelledTickets', PivotWrites::class],
            'a has many through relation' => [new Team, 'replies', NoWrites::class],
            'a has one through relation' => [new Desk, 'leadSummary', NoWrites::class],
            'a relation of another class' => [new ShelvedLabel, 'siblings', NoWrites::class],
        ];
    }

    #[Test]
    public function it_refuses_to_write_a_relation_that_takes_no_write(): void
    {
        $writes = RelationWrites::of(new Team, HasManyThrough::make('replies', ReplyResource::class), []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The relation [replies] cannot be written.');

        $writes->after(new Team, new Mutator(MutateRequest::create('/api/teams/mutate', 'POST'), new RowSet), new OperationLog);
    }

    #[Test]
    public function it_refuses_to_write_a_morph_to_relation_whose_model_method_returns_another_relation(): void
    {
        $writes = RelationWrites::of(new Loan, MorphTo::make('book', BookResource::class), []);

        $this->expectExceptionObject(new LogicException('The relation [book] is declared with MorphTo, but its model method does not return a MorphTo relation.'));

        $writes->owned(new Loan);
    }
}
