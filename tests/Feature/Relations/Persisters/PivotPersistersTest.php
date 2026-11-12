<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Event;
use Lomkit\Rest\Events\ResourceMutated;
use Lomkit\Rest\Exceptions\PivotPersisterException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Mutations\EloquentPivots;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\ForbiddingPivots;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\IdleAttachPivots;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\IdleDetachPivots;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\PersistedTagController;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\PersistedTagResource;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\PersistedTicketController;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\PersistedTicketResource;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\RecordingPivots;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\RefusingPivots;
use Lomkit\Rest\Tests\Feature\Relations\WeightedPivotTestCase;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
class PivotPersistersTest extends WeightedPivotTestCase
{
    private bool $native = false;

    /** @var array<string, Model> */
    private array $named = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('persisted-tickets', PersistedTicketController::class)->register();
        Rest::resource('persisted-tags', PersistedTagController::class)->register();
        RecordingPivots::$calls = [];
        RecordingPivots::$users = [];
        PersistedTicketResource::$persister = RecordingPivots::class;
    }

    /**
     * @param  array<string, array<string, int>>  $linked
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $calls
     * @param  list<string>  $rows
     */
    #[Test]
    #[DataProvider('decompositions')]
    public function it_splits_each_operation_into_the_persister_calls(string $relation, array $linked, array $entry, array $calls, array $rows): void
    {
        $this->link($relation, $linked);

        $response = $this->mutate($relation, $this->resolve($relation, $entry));

        $response->assertOk()->assertExactJson($this->operations($relation, $entry));
        $this->assertSame(array_map(fn (string $call): string => $this->symbolsToKeys($relation, $call), $calls), RecordingPivots::$calls);
        $this->assertSame($rows, $this->linked($relation));
    }

    /**
     * @param  array<string, array<string, int>>  $linked
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $calls
     * @param  list<string>  $rows
     */
    #[Test]
    #[DataProvider('decompositions')]
    public function it_writes_the_same_rows_with_eloquent_pivots_declared(string $relation, array $linked, array $entry, array $calls, array $rows): void
    {
        foreach ([true, false] as $native) {
            $this->unlinkAll($relation);
            $this->native = $native;
            PersistedTicketResource::$persister = EloquentPivots::class;
            $this->link($relation, $linked);

            $response = $this->mutate($relation, $this->resolve($relation, $entry));

            $response->assertOk()->assertExactJson($this->operations($relation, $entry));
            $this->assertSame($rows, $this->linked($relation));
        }

        $this->assertSame([], RecordingPivots::$calls);
    }

    /** @return iterable<string, array{string, array<string, array<string, int>>, array<string, mixed>, list<string>, list<string>}> */
    public static function decompositions(): iterable
    {
        $a = ['a' => ['position' => 1, 'weight' => 1]];
        $ab = [...$a, 'b' => ['position' => 2, 'weight' => 2]];
        $cases = [
            'create' => [$a, ['operation' => 'create', 'pivot' => ['weight' => 3]], ['attach [new] {"weight":3}'], ['a 1 1', 'new - 3']],
            'attach' => [$a, ['operation' => 'attach', 'key' => ['b'], 'pivot' => ['weight' => 4]], ['attach [b] {"weight":4}'], ['a 1 1', 'b - 4']],
            'detach' => [$ab, ['operation' => 'detach', 'key' => ['a']], ['detach [a] []'], ['b 2 2']],
            'sync' => [$ab, ['operation' => 'sync', 'key' => ['b', 'c'], 'pivot' => ['position' => 9]], ['detach [a] []', 'attach [c] {"position":9}', 'updatePivot [b] {"position":9}'], ['b 9 2', 'c 9 -']],
            'sync without detaching' => [$ab, ['operation' => 'sync', 'key' => ['b', 'c'], 'pivot' => ['position' => 9], 'without_detaching' => true], ['attach [c] {"position":9}', 'updatePivot [b] {"position":9}'], ['a 1 1', 'b 9 2', 'c 9 -']],
            'toggle' => [$a, ['operation' => 'toggle', 'key' => ['a', 'b']], ['detach [a] []', 'attach [b] []'], ['b - -']],
            'update of a linked row with pivot' => [$a, ['operation' => 'update', 'key' => 'a', 'pivot' => ['position' => 9]], ['updatePivot [a] {"position":9}'], ['a 9 1']],
            'update of a linked row without pivot' => [$a, ['operation' => 'update', 'key' => 'a'], [], ['a 1 1']],
            'update of an unlinked row' => [$a, ['operation' => 'update', 'key' => 'b', 'pivot' => ['position' => 9]], ['attach [b] {"position":9}'], ['a 1 1', 'b 9 -']],
        ];

        foreach (self::relations() as $name => [$relation]) {
            foreach ($cases as $case => [$linked, $entry, $calls, $rows]) {
                yield $name.', '.$case => [$relation, $linked, $entry, $calls, $rows];
            }
        }
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_gives_the_persister_the_user_of_the_request(string $relation): void
    {
        $user = new GenericUser(['id' => 7]);
        $this->actingAs($user);
        $this->link($relation, ['a' => ['position' => 1, 'weight' => 1], 'b' => ['position' => 2, 'weight' => 2]]);

        $response = $this->mutate($relation, $this->resolve($relation, ['operation' => 'sync', 'key' => ['b', 'c'], 'pivot' => ['position' => 9]]));

        $response->assertOk();
        $this->assertSame([['detach', $user], ['attach', $user], ['updatePivot', $user]], RecordingPivots::$users);
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_gives_the_persister_no_user_for_a_guest(string $relation): void
    {
        $this->link($relation, ['a' => ['position' => 1, 'weight' => 1], 'b' => ['position' => 2, 'weight' => 2]]);

        $response = $this->mutate($relation, $this->resolve($relation, ['operation' => 'sync', 'key' => ['b', 'c'], 'pivot' => ['position' => 9]]));

        $response->assertOk();
        $this->assertSame([['detach', null], ['attach', null], ['updatePivot', null]], RecordingPivots::$users);
    }

    #[Test]
    public function it_calls_the_persister_once_per_parent_row(): void
    {
        $other = Ticket::create(['team_id' => $this->billing->id, 'title' => 'Paper out', 'status' => 'open']);
        $tickets = [$this->jam->id, $other->id];

        $response = $this->postJson('/persisted-tags/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->hardware->id,
            'relations' => ['labelledTickets' => [[
                'operation' => 'attach',
                'key' => $tickets,
                'pivot' => ['weight' => 5],
                'relations' => ['tags' => [['operation' => 'attach', 'key' => [$this->hardware->id], 'pivot' => ['weight' => 6]]]],
            ]]],
        ]]]);

        $response->assertOk();
        $tagged = 'attach ['.$this->hardware->id.'] {"weight":6}';
        $this->assertSame([$tagged, $tagged, 'attach ['.implode(', ', $tickets).'] {"weight":5}'], RecordingPivots::$calls);
        $this->assertSame([6], $this->jam->tags()->pluck('weight')->all());
        $this->assertSame([6], $other->tags()->pluck('weight')->all());
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_answers_the_validation_errors_of_a_persister_and_writes_nothing(string $relation): void
    {
        PersistedTicketResource::$persister = RefusingPivots::class;
        Event::fake([ResourceMutated::class]);
        $this->link($relation, ['a' => ['position' => 1, 'weight' => 1]]);
        $other = $this->other($relation, 'b');
        $renamed = $relation === 'labelledTickets' ? ['title' => 'Paper gone'] : ['name' => 'Retired'];
        $column = array_key_first($renamed);
        $before = $other->getAttribute($column);

        $response = $this->mutate($relation, ['operation' => 'update', 'key' => $other->getKey(), 'attributes' => $renamed, 'pivot' => ['weight' => 4]]);

        $key = self::shown($relation).'.key';
        $response->assertUnprocessable()->assertExactJson(['message' => 'This tag is retired.', 'errors' => [$key => ['This tag is retired.']]]);
        $this->assertSame($before, $other->refresh()->getAttribute($column));
        $this->assertSame(['a 1 1'], $this->linked($relation));
        Event::assertNotDispatched(ResourceMutated::class);
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_answers_403_when_a_persister_refuses(string $relation): void
    {
        PersistedTicketResource::$persister = ForbiddingPivots::class;

        $response = $this->mutate($relation, ['operation' => 'attach', 'key' => [$this->related($relation)->getKey()], 'pivot' => ['weight' => 4]]);

        $response->assertForbidden()->assertExactJson(['message' => 'Only the helpdesk lead tags tickets.']);
        $this->assertSame([], $this->linked($relation));
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, string>  $answer
     */
    #[Test]
    #[DataProvider('denials')]
    public function it_checks_the_policies_and_link_abilities_before_a_custom_persister(string $name, bool $linked, string $status, array $entry, int $code, array $answer): void
    {
        $tag = Tag::create(['name' => $name]);
        $this->jam->update(['status' => $status]);

        if ($linked) {
            $this->jam->tags()->attach($tag->id, ['weight' => 1]);
        }

        $response = $this->mutate('tags', ['key' => [$tag->id], ...$entry]);

        $response->assertStatus($code)->assertExactJson($answer);
        $this->assertSame([], RecordingPivots::$calls);
    }

    /** @return array<string, array{string, bool, string, array<string, mixed>, int, array<string, string>}> */
    public static function denials(): array
    {
        return [
            'the attach ability' => ['Urgent', false, 'open', ['operation' => 'attach', 'pivot' => ['weight' => 1]], 409, ['message' => 'Urgent tickets are flagged by the escalation desk.']],
            'the detach ability' => ['Urgent', true, 'open', ['operation' => 'detach'], 409, ['message' => 'Urgent tickets are cleared by the escalation desk.']],
            'the update policy' => ['Billing', false, 'closed', ['operation' => 'attach', 'pivot' => ['weight' => 1]], 423, ['message' => 'Closed tickets are read-only.']],
        ];
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_refuses_a_persister_that_does_not_link_what_it_attaches(string $relation): void
    {
        PersistedTicketResource::$persister = IdleAttachPivots::class;
        $this->withoutExceptionHandling();
        $key = $this->related($relation)->getKey();

        $this->expectExceptionObject(PivotPersisterException::unattached(IdleAttachPivots::class, $this->resource($relation), $relation, [$key]));

        $this->mutate($relation, ['operation' => 'attach', 'key' => [$key], 'pivot' => ['weight' => 4]]);
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_refuses_a_persister_that_keeps_what_it_detaches(string $relation): void
    {
        PersistedTicketResource::$persister = IdleDetachPivots::class;
        $this->withoutExceptionHandling();
        $this->link($relation, ['a' => ['position' => 1, 'weight' => 1]]);
        $key = $this->related($relation)->getKey();

        $this->expectExceptionObject(PivotPersisterException::undetached(IdleDetachPivots::class, $this->resource($relation), $relation, [$key]));

        $this->mutate($relation, ['operation' => 'detach', 'key' => [$key]]);
    }

    #[\Override]
    protected function endpoint(string $relation): string
    {
        if ($this->native) {
            return parent::endpoint($relation);
        }

        return $relation === 'labelledTickets' ? '/persisted-tags' : '/persisted-tickets';
    }

    private function resource(string $relation): string
    {
        return $relation === 'labelledTickets' ? PersistedTagResource::class : PersistedTicketResource::class;
    }

    private function other(string $relation, string $symbol): Model
    {
        if ($symbol === 'a') {
            return $this->related($relation);
        }

        return $this->named[$symbol] ??= $relation === 'labelledTickets'
            ? Ticket::create(['team_id' => $this->billing->id, 'title' => $symbol === 'b' ? 'Paper out' : 'Fan noise', 'status' => 'open'])
            : Tag::create(['name' => $symbol === 'b' ? 'Billing' : 'Login']);
    }

    /** @param array<string, array<string, int>> $linked */
    private function link(string $relation, array $linked): void
    {
        foreach ($linked as $symbol => $pivot) {
            $this->parent($relation)
                ->{$relation}()
                ->attach($this->other($relation, $symbol)->getKey(), $pivot);
        }
    }

    private function unlinkAll(string $relation): void
    {
        $this->parent($relation)
            ->{$relation}()
            ->detach();
        Ticket::query()
            ->where('title', 'Toner low')
            ->delete();
        Tag::query()
            ->where('name', 'Network')
            ->forceDelete();
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function resolve(string $relation, array $entry): array
    {
        if ($entry['operation'] === 'create') {
            return [...$entry, 'attributes' => $relation === 'labelledTickets' ? ['team_id' => $this->billing->id, 'title' => 'Toner low', 'status' => 'open'] : ['name' => 'Network']];
        }

        $key = $entry['key'];

        return [...$entry, 'key' => is_array($key) ? array_map(fn (mixed $symbol): mixed => $this->keyOf($relation, $symbol), $key) : $this->keyOf($relation, $key)];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function operations(string $relation, array $entry): array
    {
        $key = $entry['operation'] === 'create' ? $this->keyOf($relation, 'new') : $this->resolve($relation, $entry)['key'];

        return ['operations' => [[
            'operation' => 'update',
            'key' => $this->parent($relation)->getKey(),
            'relations' => [$relation => [['operation' => $entry['operation'], 'key' => $key]]],
        ]]];
    }

    private function keyOf(string $relation, mixed $symbol): mixed
    {
        if ($symbol !== 'new') {
            return $this->other($relation, (string) $symbol)->getKey();
        }

        return $relation === 'labelledTickets'
            ? Ticket::query()->where('title', 'Toner low')->value('id')
            : Tag::query()->where('name', 'Network')->value('id');
    }

    private function symbolsToKeys(string $relation, string $call): string
    {
        return preg_replace_callback('/\[(\w+)\]/', fn (array $match): string => '['.$this->keyOf($relation, $match[1]).']', $call) ?? $call;
    }

    /** @return list<string> */
    private function linked(string $relation): array
    {
        $symbols = [];

        foreach (['a', 'b', 'c'] as $symbol) {
            if ($symbol === 'a' || isset($this->named[$symbol])) {
                $symbols[$this->other($relation, $symbol)->getKey()] = $symbol;
            }
        }

        $rows = $this->parent($relation)
            ->{$relation}()
            ->get()
            ->map(function (Model $related) use ($symbols): string {
                $pivot = $related->getRelation('pivot');
                $this->assertInstanceOf(Pivot::class, $pivot);

                return implode(' ', [$symbols[$related->getKey()] ?? 'new', $pivot->getAttribute('position') ?? '-', $pivot->getAttribute('weight') ?? '-']);
            })
            ->sort()
            ->values()
            ->all();

        return $rows;
    }
}
