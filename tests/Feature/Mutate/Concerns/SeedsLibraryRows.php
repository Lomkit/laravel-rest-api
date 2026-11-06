<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;

trait SeedsLibraryRows
{
    private function seedLibrary(): void
    {
        Book::query()->insert([
            ['id' => 101, 'title' => 'Dune'],
            ['id' => 102, 'title' => 'Emma'],
            ['id' => 103, 'title' => 'Hidden'],
            ['id' => 106, 'title' => 'Ulysses'],
            ['id' => 107, 'title' => 'Walden'],
        ]);
        Loan::query()->insert([
            ['id' => 101, 'book_id' => 101, 'borrower' => 'Ada'],
            ['id' => 102, 'book_id' => 102, 'borrower' => 'Grace'],
            ['id' => 103, 'book_id' => null, 'borrower' => 'Alan'],
            ['id' => 104, 'book_id' => 106, 'borrower' => 'Hidden'],
        ]);
        Label::query()->insert([
            ['id' => 101, 'labelable_type' => Book::class, 'labelable_id' => 101, 'text' => 'classic'],
            ['id' => 102, 'labelable_type' => Book::class, 'labelable_id' => 102, 'text' => 'modern'],
            ['id' => 103, 'labelable_type' => null, 'labelable_id' => null, 'text' => 'loose'],
            ['id' => 104, 'labelable_type' => Shelf::class, 'labelable_id' => 101, 'text' => 'shelved'],
        ]);
        Shelf::query()->insert([['id' => 101, 'name' => 'Classics'], ['id' => 102, 'name' => 'Poetry'], ['id' => 103, 'name' => 'Hidden']]);
        DB::table('book_shelf')->insert([['book_id' => 101, 'shelf_id' => 101], ['book_id' => 101, 'shelf_id' => 103]]);
    }

    /** @return array<string, list<string>> */
    private static function library(): array
    {
        return [
            'books' => Book::query()
                ->orderBy('title')
                ->pluck('title')
                ->all(),
            'loans' => Loan::query()
                ->with('book')
                ->orderBy('borrower')
                ->get()
                ->map(fn (Loan $loan): string => $loan->getAttribute('borrower').' > '.self::named($loan->getRelationValue('book')))
                ->all(),
            'labels' => Label::query()
                ->with('labelable')
                ->orderBy('text')
                ->get()
                ->map(fn (Label $label): string => $label->getAttribute('text').' > '.($label->getAttribute('labelable_type') === null ? '-' : class_basename((string) $label->getAttribute('labelable_type'))).' '.self::named($label->getRelationValue('labelable')))
                ->all(),
            'shelves' => Book::query()
                ->with('shelves')
                ->orderBy('title')
                ->get()
                ->flatMap(fn (Book $book): array => self::shelvesOf($book))
                ->all(),
        ];
    }

    /** @return list<string> */
    private static function shelvesOf(Book $book): array
    {
        return $book
            ->getRelationValue('shelves')
            ->map(fn (Shelf $shelf): string => $book->getAttribute('title').' > '.$shelf->getAttribute('name'))
            ->sort()
            ->values()
            ->all();
    }

    private static function named(mixed $model): string
    {
        return match (true) {
            $model instanceof Book => (string) $model->getAttribute('title'),
            $model instanceof Model => (string) $model->getAttribute('name'),
            default => '-',
        };
    }
}
