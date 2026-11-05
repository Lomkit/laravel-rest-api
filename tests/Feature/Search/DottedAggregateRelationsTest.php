<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Illustrated\IllustratedStoryController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Illustrated\LeadDeskController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ThreadedCommentController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Newsroom;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Photo;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Story;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class DottedAggregateRelationsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Library, new Helpdesk, new Newsroom];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('lead-desks', LeadDeskController::class)->register();
        Rest::resource('illustrated-stories', IllustratedStoryController::class)->register();
        Rest::resource('threaded-comments', ThreadedCommentController::class)->register();
        $this->seedLibrary();
        $this->seedHelpdesk();
        $this->seedNewsroom();
    }

    /** @param list<int> $counts */
    #[Test]
    #[DataProvider('paths')]
    public function it_counts_the_rows_at_the_end_of_a_path_through_each_relation_class(string $uri, string $relation, string $key, array $counts): void
    {
        $response = $this->postJson($uri, ['search' => [
            'selects' => [['field' => 'id']],
            'aggregates' => [['relation' => $relation, 'type' => 'count']],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk();
        $this->assertSame($counts, array_column($response->json('data'), $key));
    }

    /** @return array<string, array{string, string, string, list<int>}> */
    public static function paths(): array
    {
        return [
            'through a belongs to, to a has one' => ['/api/loans/search', 'book.loan', 'book_loan_count', [2, 2, 1]],
            'to a has one of many' => ['/api/loans/search', 'book.latestLoan', 'book_latest_loan_count', [1, 1, 1]],
            'to a morph one' => ['/api/loans/search', 'book.label', 'book_label_count', [2, 2, 1]],
            'to a morph one of many' => ['/api/loans/search', 'book.latestLabel', 'book_latest_label_count', [1, 1, 1]],
            'to a morph many' => ['/api/loans/search', 'book.labels', 'book_labels_count', [2, 2, 1]],
            'to a belongs to many' => ['/api/loans/search', 'book.shelves', 'book_shelves_count', [2, 2, 1]],
            'through a has one, to a belongs to' => ['/api/books/search', 'loan.book', 'loan_book_count', [1, 1, 0]],
            'through a has one of many' => ['/api/books/search', 'latestLoan.book', 'latest_loan_book_count', [1, 1, 0]],
            'through a has many, to a morph to many' => ['/api/teams/search', 'tickets.labels', 'tickets_labels_count', [2, 1]],
            'through a belongs to many, to a morphed by many' => ['/api/tickets/search', 'tags.labelledTickets', 'tags_labelled_tickets_count', [0, 2, 0]],
            'through a morph to many' => ['/api/tickets/search', 'labels.labelledTickets', 'labels_labelled_tickets_count', [2, 1, 2]],
            'through a morphed by many' => ['/api/tags/search', 'labelledTickets.team', 'labelled_tickets_team_count', [2, 1]],
            'to a has many' => ['/illustrated-stories/search', 'desk.stories', 'desk_stories_count', [2, 2, 1]],
            'through a morph one' => ['/illustrated-stories/search', 'cover.keywords', 'cover_keywords_count', [2, 0, 1]],
            'through a morph many' => ['/illustrated-stories/search', 'photos.keywords', 'photos_keywords_count', [2, 0, 1]],
            'through a morph one of many' => ['/illustrated-stories/search', 'latestPhoto.keywords', 'latest_photo_keywords_count', [1, 0, 1]],
            'to a has one through' => ['/illustrated-stories/search', 'desk.leadSummary', 'desk_lead_summary_count', [2, 2, 1]],
            'to a has many through' => ['/illustrated-stories/search', 'desk.comments', 'desk_comments_count', [4, 4, 1]],
            'through a has one through' => ['/lead-desks/search', 'leadSummary.story', 'lead_summary_story_count', [1, 1]],
            'through a has many through' => ['/lead-desks/search', 'comments.story', 'comments_story_count', [2, 1]],
            'through relations to the same table' => ['/threaded-comments/search', 'replies.replies', 'replies_replies_count', [1, 0, 0, 0, 0, 0]],
            'through a relation to the same table and back' => ['/threaded-comments/search', 'parent.replies', 'parent_replies_count', [0, 0, 1, 0, 1, 1]],
        ];
    }

    private function seedLibrary(): void
    {
        DB::table('books')->insert([['id' => 1, 'title' => 'Dune'], ['id' => 2, 'title' => 'Emma'], ['id' => 3, 'title' => 'Ulysses']]);
        DB::table('loans')->insert([['id' => 1, 'book_id' => 1, 'borrower' => 'Zoe'], ['id' => 2, 'book_id' => 1, 'borrower' => 'Adam'], ['id' => 3, 'book_id' => 2, 'borrower' => 'Mia']]);

        foreach ([[1, 2, 'Classic'], [2, 3, 'Archive'], [3, 1, 'New'], [4, 1, 'Signed']] as [$id, $book, $text]) {
            DB::table('labels')->insert(['id' => $id, 'labelable_type' => Book::class, 'labelable_id' => $book, 'text' => $text]);
        }

        DB::table('shelves')->insert([['id' => 1, 'name' => 'Classics'], ['id' => 2, 'name' => 'Sci-fi']]);
        DB::table('book_shelf')->insert([['book_id' => 1, 'shelf_id' => 1], ['book_id' => 1, 'shelf_id' => 2], ['book_id' => 2, 'shelf_id' => 1]]);
    }

    private function seedHelpdesk(): void
    {
        DB::table('teams')->insert([['id' => 1, 'name' => 'Core'], ['id' => 2, 'name' => 'Ops']]);

        foreach ([[1, 1, 'Printer jam'], [2, 1, 'Paper jam'], [3, 2, 'Fan noise']] as [$id, $team, $title]) {
            DB::table('tickets')->insert(['id' => $id, 'team_id' => $team, 'title' => $title, 'status' => 'open']);
        }

        DB::table('tags')->insert([['id' => 1, 'name' => 'Hardware'], ['id' => 2, 'name' => 'Network']]);
        DB::table('tag_ticket')->insert([['tag_id' => 1, 'ticket_id' => 2]]);

        foreach ([[1, 1], [1, 3], [2, 2]] as [$tag, $ticket]) {
            DB::table('labelables')->insert(['tag_id' => $tag, 'labelable_type' => Ticket::class, 'labelable_id' => $ticket]);
        }
    }

    private function seedNewsroom(): void
    {
        DB::table('desks')->insert([['id' => 1, 'name' => 'City'], ['id' => 2, 'name' => 'Sport']]);

        foreach ([[1, 1, 'Bridge'], [2, 1, 'Tunnel'], [3, 1, 'Hidden'], [4, 2, 'Derby']] as [$id, $desk, $headline]) {
            DB::table('stories')->insert(['id' => $id, 'desk_id' => $desk, 'headline' => $headline]);
        }

        DB::table('summaries')->insert([['id' => 1, 'story_id' => 1, 'body' => 'Mild'], ['id' => 2, 'story_id' => 3, 'body' => 'Big'], ['id' => 3, 'story_id' => 4, 'body' => 'Calm']]);

        foreach ([[1, 1, null, 'urgent'], [2, 2, null, 'urgent'], [3, 2, 2, 'spam'], [4, 3, null, 'calm'], [5, 4, 1, 'ok'], [6, 1, 5, 'deep']] as [$id, $story, $parent, $body]) {
            DB::table('comments')->insert(['id' => $id, 'story_id' => $story, 'parent_id' => $parent, 'body' => $body]);
        }

        foreach ([[1, 1, 'a.png'], [2, 1, 'b.png'], [3, 2, 'hidden.png'], [4, 4, 'c.png']] as [$id, $story, $path]) {
            DB::table('photos')->insert(['id' => $id, 'imageable_type' => Story::class, 'imageable_id' => $story, 'path' => $path]);
        }

        DB::table('keywords')->insert([['id' => 1, 'name' => 'news'], ['id' => 2, 'name' => 'sport']]);

        foreach ([[1, 1], [2, 2], [2, 3], [2, 4]] as [$keyword, $photo]) {
            DB::table('keywordables')->insert(['keyword_id' => $keyword, 'keywordable_type' => Photo::class, 'keywordable_id' => $photo]);
        }
    }
}
