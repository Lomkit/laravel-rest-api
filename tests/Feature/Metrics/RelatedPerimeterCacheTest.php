<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\BookLabelController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MorphLabelController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\RevealedTagTicketController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TaggedTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('cache.serializable_classes', false)]
class RelatedPerimeterCacheTest extends HelpdeskTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('tagged-tickets', TaggedTicketController::class)->register();
        Rest::resource('morph-labels', MorphLabelController::class)->register();
        Rest::resource('book-labels', BookLabelController::class)->register();
        Rest::resource('revealed-tag-tickets', RevealedTagTicketController::class)->register();
        Team::create(['id' => 1, 'name' => 'Support']);
        $ticket = Ticket::create(['id' => 1, 'team_id' => 1, 'title' => 'Ticket 1', 'status' => 'open']);
        $tag = Tag::create(['id' => 1, 'name' => 'Secret']);
        $ticket->tags()->attach($tag->id);
        $ticket->labels()->attach($tag->id);
        Label::create(['id' => 1, 'text' => 'Label 1', 'labelable_type' => Book::class, 'labelable_id' => Book::create(['id' => 1, 'title' => 'Secret'])->id]);
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk, new Library];
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('relatedSearches')]
    public function it_keeps_a_global_result_of_each_related_perimeter_apart(array $search): void
    {
        $contents = $this->askAsUsers('/tagged-tickets/metrics/ticket-backlog', $search);

        $seen = '{"data":{"type":"gauge","value":1,"max":10}}';
        $hidden = '{"data":{"type":"gauge","value":0,"max":10}}';
        $this->assertSame([$seen, $hidden, $seen, $hidden], $contents);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function relatedSearches(): array
    {
        return [
            'a relation filter' => [['filters' => [['field' => 'tags.name', 'value' => 'Secret']]]],
            'a relation group' => [['filters' => [['relation' => 'tags', 'nested' => [['field' => 'name', 'value' => 'Secret']]]]]],
            'a relation filter in a nested group' => [['filters' => [['nested' => [['field' => 'tags.name', 'value' => 'Secret']]]]]],
            'a morph to many filter' => [['filters' => [['field' => 'labels.name', 'value' => 'Secret']]]],
            'a morph to many group' => [['filters' => [['relation' => 'labels', 'nested' => [['field' => 'name', 'value' => 'Secret']]]]]],
        ];
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('morphSearches')]
    public function it_keeps_a_global_result_of_each_morph_perimeter_apart(string $resource, array $search): void
    {
        $contents = $this->askAsUsers("/{$resource}/metrics/row-count", $search);

        $seen = '{"data":{"type":"count","value":1}}';
        $hidden = '{"data":{"type":"count","value":0}}';
        $this->assertSame([$seen, $hidden, $seen, $hidden], $contents);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function morphSearches(): array
    {
        return [
            'a morph group of a type' => ['morph-labels', ['filters' => [['relation' => 'labelable', 'types' => [Book::class => [['field' => 'title', 'value' => 'Secret']]]]]]],
            'a morph group of every type' => ['morph-labels', ['filters' => [['relation' => 'labelable']]]],
            'a morph group in a nested group' => ['morph-labels', ['filters' => [['nested' => [['relation' => 'labelable', 'types' => [Book::class => []]]]]]]],
            'a filter through a morph relation of one type' => ['book-labels', ['filters' => [['field' => 'labelable.title', 'value' => 'Secret']]]],
            'a group of a morph relation of one type' => ['book-labels', ['filters' => [['relation' => 'labelable', 'nested' => [['field' => 'title', 'value' => 'Secret']]]]]],
        ];
    }

    #[Test]
    #[DataProvider('scopes')]
    public function it_keys_the_context_of_each_reached_resource(string $metric, string $seen, string $hidden): void
    {
        Tag::addGlobalScope('secret', fn (Builder $query): Builder => config()->boolean('helpdesk.reveal_secret_tags', false) ? $query : $query->where('tags.name', '!=', 'Secret'));
        $contents = [];

        foreach ([false, true, false] as $reveal) {
            config(['helpdesk.reveal_secret_tags' => $reveal]);
            $contents[] = $this->actingAs(new GenericUser(['id' => 1]))
                ->postJson("/revealed-tag-tickets/metrics/{$metric}", ['search' => ['filters' => [['field' => 'tags.name', 'value' => 'Secret']]]])
                ->assertOk()
                ->getContent();
        }

        $this->assertSame([$hidden, $seen, $hidden], $contents);
    }

    /** @return array<string, array{string, string, string}> */
    public static function scopes(): array
    {
        return [
            'each user' => ['ticket-backlog', '{"data":{"type":"gauge","value":1,"max":10}}', '{"data":{"type":"gauge","value":0,"max":10}}'],
            'every user' => ['row-count', '{"data":{"type":"count","value":1}}', '{"data":{"type":"count","value":0}}'],
        ];
    }

    /**
     * @param  array<string, mixed>  $search
     * @return list<string|false>
     */
    private function askAsUsers(string $uri, array $search): array
    {
        $contents = [];

        foreach ([1, 2, 1, 2] as $user) {
            $contents[] = $this->actingAs(new GenericUser(['id' => $user]))
                ->postJson($uri, ['search' => $search])
                ->assertOk()
                ->getContent();
        }

        return $contents;
    }
}
