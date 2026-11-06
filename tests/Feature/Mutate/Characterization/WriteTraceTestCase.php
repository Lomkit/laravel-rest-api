<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Characterization;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;

abstract class WriteTraceTestCase extends PackageTestCase
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

        Carbon::setTestNow('2026-03-01 09:00:00');
        TracingPolicy::$trace = [];

        foreach ([Book::class, Loan::class, Label::class, Shelf::class, Ticket::class, Tag::class, Team::class] as $model) {
            Gate::policy($model, TracingPolicy::class);
        }

        Book::query()->insert([['id' => 101, 'title' => 'Dune'], ['id' => 102, 'title' => 'Emma']]);
        Shelf::query()->insert([['id' => 101, 'name' => 'Classics'], ['id' => 102, 'name' => 'Poetry']]);
        Loan::query()->insert([['id' => 101, 'book_id' => 101, 'borrower' => 'Ada'], ['id' => 102, 'book_id' => null, 'borrower' => 'Grace']]);
        Label::query()->insert([['id' => 101, 'labelable_type' => Book::class, 'labelable_id' => 101, 'text' => 'classic'], ['id' => 102, 'labelable_type' => null, 'labelable_id' => null, 'text' => 'loose']]);
        DB::table('book_shelf')->insert(['book_id' => 101, 'shelf_id' => 101]);
        Team::query()->insert(['id' => 101, 'name' => 'Support']);
        Ticket::query()->insert([['id' => 101, 'team_id' => 101, 'title' => 'Printer jam', 'status' => 'open'], ['id' => 102, 'team_id' => 101, 'title' => 'Password reset', 'status' => 'open']]);
        Tag::query()->insert([['id' => 101, 'name' => 'Hardware'], ['id' => 102, 'name' => 'Network']]);
        DB::table('tag_ticket')->insert(['tag_id' => 101, 'ticket_id' => 101, 'position' => 1]);

        DB::listen(function (QueryExecuted $query): void {
            TracingPolicy::$trace[] = 'sql '.preg_replace('/\w+\.updated_at = /', 'updated_at = ', str_replace(['"', '`', ' returning id'], '', $query->sql));
        });
        Event::listen('eloquent.saved: *', function (string $event, array $models): void {
            TracingPolicy::$trace[] = 'saved '.TracingPolicy::name($models[0]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $operations
     * @param  list<string>  $trace
     */
    protected function assertWrites(string $endpoint, array $payload, array $operations, array $trace): void
    {
        $response = $this->postJson($endpoint, $payload);

        $response->assertOk();
        $this->assertSame($trace, TracingPolicy::$trace);
        $this->assertSame(['operations' => $this->keys($operations)], $response->json());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $queries
     * @param  list<string>|null  $policies
     */
    protected function assertQueries(string $endpoint, array $payload, array $queries, ?array $policies = null): void
    {
        $this->postJson($endpoint, $payload)->assertOk();

        $this->assertSame($queries, array_values(array_map(
            fn (string $line): string => substr($line, 4),
            array_filter(TracingPolicy::$trace, fn (string $line): bool => str_starts_with($line, 'sql ')),
        )));

        if ($policies !== null) {
            $this->assertSame($policies, array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => str_starts_with($line, 'policy '))));
        }
    }

    /** @return list<string> */
    protected static function rows(string $table): array
    {
        return match ($table) {
            'books' => self::lines(Book::query()->with('shelves'), 'title', fn (Book $book): string => TracingPolicy::name($book).' '.self::related($book, 'shelves')),
            'loans' => self::lines(Loan::query()->with('book'), 'borrower', fn (Loan $loan): string => TracingPolicy::name($loan).' '.self::target($loan->getRelationValue('book'))),
            'labels' => self::lines(Label::query()->with('labelable'), 'text', fn (Label $label): string => TracingPolicy::name($label).' '.self::type($label).' '.self::target($label->getRelationValue('labelable'))),
            'tickets' => self::lines(Ticket::query()->with('tags', 'labels'), 'title', fn (Ticket $ticket): string => TracingPolicy::name($ticket).' '.$ticket->getAttribute('status').' '.self::pivots($ticket, 'tags').' '.self::pivots($ticket, 'labels')),
        };
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  Closure(TModel): string  $line
     * @return list<string>
     */
    private static function lines(Builder $query, string $column, Closure $line): array
    {
        $models = $query
            ->orderBy($column)
            ->get()
            ->all();

        return array_values(array_map($line, $models));
    }

    private static function target(mixed $model): string
    {
        return $model instanceof Model ? TracingPolicy::name($model) : '-';
    }

    private static function type(Label $label): string
    {
        $type = $label->getAttribute('labelable_type');

        return $type === null ? '-' : class_basename((string) $type);
    }

    private static function related(Model $model, string $relation): string
    {
        $names = $model
            ->getRelationValue($relation)
            ->map(TracingPolicy::name(...))
            ->sort()
            ->all();

        return '['.implode(', ', $names).']';
    }

    private static function pivots(Ticket $ticket, string $relation): string
    {
        $names = $ticket
            ->getRelationValue($relation)
            ->map(fn (Model $related): string => TracingPolicy::name($related).' '.($related->getRelationValue('pivot')->getAttribute('position') ?? '-'))
            ->sort()
            ->all();

        return '['.implode(', ', $names).']';
    }

    /**
     * @param  array<array-key, mixed>  $expected
     * @return array<array-key, mixed>
     */
    private function keys(array $expected): array
    {
        foreach ($expected as $name => $value) {
            if (is_array($value)) {
                $expected[$name] = $this->keys($value);
            } elseif (is_string($value) && preg_match('/^(Book|Loan|Label|Shelf|Tag|Ticket)\((.+)\)$/', $value, $match) === 1) {
                $expected[$name] = self::key($match[1], $match[2]);
            }
        }

        return $expected;
    }

    private static function key(string $model, string $name): mixed
    {
        /** @var class-string<Model> $class */
        $class = ['Book' => Book::class, 'Loan' => Loan::class, 'Label' => Label::class, 'Shelf' => Shelf::class, 'Tag' => Tag::class, 'Ticket' => Ticket::class][$model];
        $column = ['Book' => 'title', 'Loan' => 'borrower', 'Label' => 'text', 'Shelf' => 'name', 'Tag' => 'name', 'Ticket' => 'title'][$model];

        return $class::query()
            ->where($column, $name)
            ->sole()
            ->getKey();
    }
}
