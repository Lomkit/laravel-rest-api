<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class EmptyKeyListsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Library, new Helpdesk];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Book::query()->insert([['id' => 101, 'title' => 'Dune'], ['id' => 102, 'title' => 'Emma']]);
        Shelf::query()->insert([['id' => 101, 'name' => 'Classics'], ['id' => 102, 'name' => 'Poetry']]);
        DB::table('book_shelf')->insert(['book_id' => 101, 'shelf_id' => 101]);
        Label::query()->insert(['id' => 101, 'labelable_type' => Book::class, 'labelable_id' => 101, 'text' => 'classic']);
        Team::query()->insert([['id' => 101, 'name' => 'Support'], ['id' => 102, 'name' => 'Ops']]);
        Ticket::query()->insert([['id' => 101, 'team_id' => 101, 'title' => 'Printer jam', 'status' => 'open'], ['id' => 102, 'team_id' => 101, 'title' => 'Password reset', 'status' => 'open']]);
        Tag::query()->insert([['id' => 101, 'name' => 'Hardware'], ['id' => 102, 'name' => 'Network']]);
        DB::table('tag_ticket')->insert([['tag_id' => 101, 'ticket_id' => 101], ['tag_id' => 102, 'ticket_id' => 101]]);
    }

    #[Test]
    #[DataProvider('emptyKeyLists')]
    public function it_writes_nothing_for_an_empty_key_list(string $uri, string $relation, string $operation): void
    {
        $rows = self::rows();

        $response = $this->postJson($uri, ['mutate' => [['operation' => 'update', 'key' => 101, 'relations' => [$relation => [['operation' => $operation, 'key' => []]]]]]]);

        $response->assertOk();
        $this->assertSame($rows, self::rows());
    }

    /** @return array<string, array{string, string, string}> */
    public static function emptyKeyLists(): array
    {
        return [
            'has many attach' => ['/api/teams/mutate', 'tickets', 'attach'],
            'morph many attach' => ['/api/books/mutate', 'labels', 'attach'],
            'belongs to many attach' => ['/api/books/mutate', 'shelves', 'attach'],
            'belongs to many detach' => ['/api/books/mutate', 'shelves', 'detach'],
            'belongs to many update' => ['/api/books/mutate', 'shelves', 'update'],
            'belongs to many toggle' => ['/api/books/mutate', 'shelves', 'toggle'],
        ];
    }

    #[Test]
    public function it_detaches_every_attached_row_for_a_sync_with_an_empty_key_list(): void
    {
        $response = $this->postJson('/api/tickets/mutate', ['mutate' => [['operation' => 'update', 'key' => 101, 'relations' => ['tags' => [['operation' => 'sync', 'key' => []]]]]]]);

        $response->assertOk();
        $this->assertSame(['Password reset' => [], 'Printer jam' => []], self::rows()['tags']);
    }

    /** @return array<string, array<string, mixed>> */
    private static function rows(): array
    {
        return [
            'shelves' => Book::query()
                ->with('shelves')
                ->orderBy('title')
                ->get()
                ->mapWithKeys(fn (Book $book): array => [(string) $book->getAttribute('title') => self::keysOf($book, 'shelves')])
                ->all(),
            'labels' => Label::query()
                ->orderBy('id')
                ->get()
                ->map(fn (Label $label): string => $label->getAttribute('text').' > '.($label->getAttribute('labelable_id') ?? '-'))
                ->all(),
            'teams' => Ticket::query()
                ->orderBy('title')
                ->pluck('team_id', 'title')
                ->all(),
            'tags' => Ticket::query()
                ->with('tags')
                ->orderBy('title')
                ->get()
                ->mapWithKeys(fn (Ticket $ticket): array => [(string) $ticket->getAttribute('title') => self::keysOf($ticket, 'tags')])
                ->all(),
        ];
    }

    /** @return list<mixed> */
    private static function keysOf(Model $model, string $relation): array
    {
        $keys = $model->getRelationValue($relation)->modelKeys();
        sort($keys);

        return $keys;
    }
}
