<?php

namespace Lomkit\Rest\Tests\Feature\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\MorphedByMany;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\AssignedTicketController;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\WeightedTagResource;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\WeightedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PivotFieldsTest extends WeightedPivotTestCase
{
    #[Test]
    #[DataProvider('relations')]
    public function it_returns_only_the_selectable_pivot_fields_in_an_include(string $relation): void
    {
        $this->attach($relation, ['position' => 1, 'weight' => 3, 'added_by' => 'Ada', 'note' => 'Seen twice', 'created_at' => '2026-01-02 09:00:00']);

        $response = $this->postJson($this->endpoint($relation).'/search', ['search' => ['includes' => [['relation' => $relation]]]]);

        $response->assertOk()->assertJsonPath('data.0.'.Str::snake($relation), [[
            ...($relation === 'labelledTickets'
                ? ['id' => $this->jam->id, 'team_id' => $this->billing->id, 'title' => 'Printer jam', 'status' => 'open']
                : ['id' => $this->hardware->id, 'name' => 'Hardware']),
            'pivot' => ['position' => 1, 'weight' => 3, 'created_at' => '2026-01-02T09:00:00.000000Z'],
        ]]);
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_filters_on_a_declared_pivot_field_and_refuses_an_undeclared_one(string $relation): void
    {
        $this->attach($relation, ['position' => 1, 'weight' => 3, 'added_by' => 'Ada', 'note' => 'Seen twice']);

        $declared = $this->postJson($this->endpoint($relation).'/search', ['search' => ['filters' => [['field' => $relation.'.pivot.weight', 'value' => 3]]]]);
        $undeclared = $this->postJson($this->endpoint($relation).'/search', ['search' => ['filters' => [['field' => $relation.'.pivot.colour', 'value' => 'red']]]]);

        $declared->assertOk()->assertJsonPath('data.*.id', [$this->parent($relation)->getKey()]);
        $undeclared->assertUnprocessable()->assertJsonPath('errors', [
            'search.filters.0.field' => ['The selected search.filters.0.field is invalid.'],
        ]);
    }

    #[Test]
    #[DataProvider('directWrites')]
    public function it_writes_only_the_writable_pivot_keys_of_the_operation(string $relation, string $operation, ?string $addedBy): void
    {
        $context = new RestContext(MutateRequest::create($this->endpoint($relation).'/mutate', 'POST'), $this->app);
        $declared = $context->resource($relation === 'labelledTickets' ? WeightedTagResource::class : WeightedTicketResource::class)->relation($relation);
        $this->assertInstanceOf(PivotRelation::class, $declared);

        $declared->{$operation}($this->parent($relation), $declared, [
            'operation' => $operation,
            'key' => $this->related($relation)->getKey(),
            'pivot' => ['weight' => 3, 'added_by' => 'Grace', 'created_at' => '2026-01-01 09:00:00', 'colour' => 'red'],
        ]);

        $this->assertSame([['position' => null, 'weight' => 3, 'added_by' => $addedBy, 'note' => null, 'created_at' => self::NOW]], $this->pivotRows($relation));
    }

    /** @return iterable<string, array{string, string, ?string}> */
    public static function directWrites(): iterable
    {
        foreach (self::relations() as $name => [$relation]) {
            yield $name.', attach' => [$relation, 'attach', 'Grace'];
            yield $name.', update' => [$relation, 'update', null];
            yield $name.', sync' => [$relation, 'sync', null];
            yield $name.', toggle' => [$relation, 'toggle', null];
        }
    }

    #[Test]
    public function it_binds_the_pivot_fields_to_the_pivot_class_of_the_relation(): void
    {
        Rest::resource('assigned-tickets', AssignedTicketController::class)->register();

        $invalid = $this->postJson('/assigned-tickets/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->jam->id,
            'relations' => ['tags' => [['operation' => 'attach', 'key' => $this->hardware->id, 'pivot' => ['weight' => 2]]]],
        ]]]);
        $valid = $this->postJson('/assigned-tickets/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->jam->id,
            'relations' => ['tags' => [['operation' => 'attach', 'key' => $this->hardware->id, 'pivot' => ['weight' => 3]]]],
        ]]]);

        $invalid->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.tags.0.pivot.weight' => ['The selected mutate.0.relations.tags.0.pivot.weight is invalid.'],
        ]);
        $valid->assertOk()->assertExactJson(['created' => [], 'updated' => [$this->jam->id]]);
        $this->assertSame([3], $this->jam
            ->tags()
            ->pluck('tag_ticket.weight')
            ->all());
    }

    #[Test]
    public function it_refuses_a_pivot_field_that_is_not_a_field_object(): void
    {
        $this->expectExceptionObject(InvalidFieldException::notAField('pivotFields() of the tags relation', 0, 'position'));

        BelongsToMany::make('tags', TagResource::class)->pivotFields(['position']);
    }

    #[Test]
    #[DataProvider('unstoredPivotFields')]
    public function it_refuses_a_pivot_field_that_is_not_written_to_its_column_as_sent(BelongsToMany|MorphToMany|MorphedByMany $relation, Field $field, InvalidFieldException $refusal): void
    {
        $this->expectExceptionObject($refusal);

        $relation->pivotFields([Integer::make('position'), $field]);
    }

    /** @return iterable<string, array{BelongsToMany|MorphToMany|MorphedByMany, Field, InvalidFieldException}> */
    public static function unstoredPivotFields(): iterable
    {
        $relations = [
            'belongs to many' => BelongsToMany::make('tags', TagResource::class),
            'morph to many' => MorphToMany::make('labels', TagResource::class),
            'morphed by many' => MorphedByMany::make('labelledTickets', TicketResource::class),
        ];

        foreach ($relations as $name => $relation) {
            $owner = 'pivotFields() of the '.$relation->relation.' relation';

            yield $name.', virtual' => [$relation, Text::make('label')->virtual(), InvalidFieldException::virtualPivotField($owner, 'label')];
            yield $name.', virtual with a fill callback' => [
                $relation,
                Text::make('label')
                    ->virtual()
                    ->fillUsing(self::fillNothing(...)),
                InvalidFieldException::virtualPivotField($owner, 'label'),
            ];
            yield $name.', fill callback' => [$relation, Text::make('note')->fillUsing(self::fillNothing(...)), InvalidFieldException::filledPivotField($owner, 'note')];
        }
    }

    private static function fillNothing(Model $pivot, mixed $value): void {}
}
