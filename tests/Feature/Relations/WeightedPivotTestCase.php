<?php

namespace Lomkit\Rest\Tests\Feature\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\WeightedTagController;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\WeightedTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\PackageTestCase;

abstract class WeightedPivotTestCase extends PackageTestCase
{
    protected const array COLUMNS = ['position', 'weight', 'added_by', 'note', 'created_at'];

    protected const string NOW = '2026-03-02 09:00:00';

    protected Team $billing;

    protected Ticket $jam;

    protected Tag $hardware;

    /** @return array<string, array{string}> */
    public static function relations(): array
    {
        return [
            'belongs to many' => ['tags'],
            'morph to many' => ['labels'],
            'morphed by many' => ['labelledTickets'],
        ];
    }

    /** @return iterable<string, array{string, string}> */
    public static function creatingOperations(): iterable
    {
        return self::operations(['attach', 'create']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function updatingOperations(): iterable
    {
        return self::operations(['update', 'sync', 'toggle']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function writingOperations(): iterable
    {
        return self::operations(['attach', 'create', 'update', 'sync', 'toggle']);
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::NOW);
        Rest::resource('weighted-tickets', WeightedTicketController::class)->register();
        Rest::resource('weighted-tags', WeightedTagController::class)->register();

        $this->billing = Team::create(['name' => 'Billing']);
        $this->jam = Ticket::create(['team_id' => $this->billing->id, 'title' => 'Printer jam', 'status' => 'open']);
        $this->hardware = Tag::create(['name' => 'Hardware']);
    }

    /** @param array<string, mixed> ...$entries */
    protected function mutate(string $relation, array ...$entries): TestResponse
    {
        return $this->postJson($this->endpoint($relation).'/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->parent($relation)->getKey(),
            'relations' => [$relation => $entries],
        ]]]);
    }

    /**
     * @param  array<string, mixed>|string  $pivot
     * @return array<string, mixed>
     */
    protected function entry(string $relation, string $operation, array|string $pivot): array
    {
        if ($operation === 'create') {
            return ['operation' => 'create', 'attributes' => $this->creation($relation), 'pivot' => $pivot];
        }

        return ['operation' => $operation, 'key' => $this->related($relation)->getKey(), 'pivot' => $pivot];
    }

    /** @param array<string, mixed> $pivot */
    protected function attach(string $relation, array $pivot): void
    {
        $this->parent($relation)
            ->{$relation}()
            ->attach($this->related($relation)->getKey(), $pivot);
    }

    /** @return list<array<string, mixed>> */
    protected function pivotRows(string $relation): array
    {
        return $this->parent($relation)
            ->{$relation}()
            ->get()
            ->map(fn (Model $related): array => $this->pivotRow($related))
            ->all();
    }

    protected function endpoint(string $relation): string
    {
        return $relation === 'labelledTickets' ? '/weighted-tags' : '/weighted-tickets';
    }

    protected function parent(string $relation): Model
    {
        return $relation === 'labelledTickets' ? $this->hardware : $this->jam;
    }

    protected function related(string $relation): Model
    {
        return $relation === 'labelledTickets' ? $this->jam : $this->hardware;
    }

    protected static function shown(string $relation, int $entry = 0): string
    {
        return 'mutate.0.relations.'.str_replace('_', ' ', Str::snake($relation)).'.'.$entry;
    }

    /** @return array<string, mixed> */
    private function creation(string $relation): array
    {
        return $relation === 'labelledTickets'
            ? ['team_id' => $this->billing->id, 'title' => 'Toner low', 'status' => 'open']
            : ['name' => 'Network'];
    }

    /** @return array<string, mixed> */
    private function pivotRow(Model $related): array
    {
        $pivot = $related->getRelation('pivot');
        $this->assertInstanceOf(Pivot::class, $pivot);

        return [...$pivot->only(self::COLUMNS), 'created_at' => $pivot->created_at?->toDateTimeString()];
    }

    /**
     * @param  list<string>  $operations
     * @return iterable<string, array{string, string}>
     */
    private static function operations(array $operations): iterable
    {
        foreach (self::relations() as $name => [$relation]) {
            foreach ($operations as $operation) {
                yield $name.', '.$operation => [$relation, $operation];
            }
        }
    }
}
