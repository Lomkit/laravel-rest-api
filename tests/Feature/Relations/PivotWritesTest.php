<?php

namespace Lomkit\Rest\Tests\Feature\Relations;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PivotWritesTest extends WeightedPivotTestCase
{
    #[Test]
    #[DataProvider('creatingOperations')]
    public function it_writes_the_creatable_pivot_fields_on_attach_and_create(string $relation, string $operation): void
    {
        $response = $this->mutate($relation, $this->entry($relation, $operation, ['position' => 1, 'weight' => 3, 'added_by' => 'Ada', 'note' => 'Seen twice']));

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$this->parent($relation)->getKey()]]);
        $this->assertSame([['position' => 1, 'weight' => 3, 'added_by' => 'Ada', 'note' => 'Seen twice', 'created_at' => self::NOW]], $this->pivotRows($relation));
    }

    #[Test]
    #[DataProvider('relationsUpdatingAnAttachedRow')]
    public function it_writes_the_updatable_pivot_fields_on_update_and_sync(string $relation, string $operation): void
    {
        $this->attach($relation, ['position' => 1, 'weight' => 1, 'added_by' => 'Ada', 'note' => 'First look']);

        $response = $this->mutate($relation, $this->entry($relation, $operation, ['position' => 2, 'weight' => 5, 'note' => 'Escalated']));

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$this->parent($relation)->getKey()]]);
        $this->assertSame([['position' => 2, 'weight' => 5, 'added_by' => 'Ada', 'note' => 'Escalated', 'created_at' => self::NOW]], $this->pivotRows($relation));
    }

    /** @return iterable<string, array{string, string}> */
    public static function relationsUpdatingAnAttachedRow(): iterable
    {
        foreach (self::updatingOperations() as $name => [$relation, $operation]) {
            if ($operation !== 'toggle') {
                yield $name => [$relation, $operation];
            }
        }
    }

    #[Test]
    #[DataProvider('updatingOperations')]
    public function it_refuses_a_pivot_field_that_is_not_updatable_on_update_sync_and_toggle(string $relation, string $operation): void
    {
        $this->attach($relation, ['position' => 1, 'weight' => 1, 'added_by' => 'Ada']);

        $response = $this->mutate($relation, $this->entry($relation, $operation, ['weight' => 2, 'added_by' => 'Grace']));

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.'.$relation.'.0.pivot' => ['The added_by attribute is not writable on update.'],
        ]);
        $this->assertSame([['position' => 1, 'weight' => 1, 'added_by' => 'Ada', 'note' => null, 'created_at' => self::NOW]], $this->pivotRows($relation));
    }

    #[Test]
    #[DataProvider('writingOperations')]
    public function it_refuses_a_readonly_and_an_undeclared_pivot_key_on_every_writing_operation(string $relation, string $operation): void
    {
        $write = in_array($operation, ['attach', 'create'], true) ? 'create' : 'update';

        $response = $this->mutate($relation, $this->entry($relation, $operation, ['weight' => 2, 'created_at' => '2026-01-01 09:00:00', 'colour' => 'red']));

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.'.$relation.'.0.pivot' => [
                'The created_at attribute is not writable on '.$write.'.',
                'The colour attribute is not writable on '.$write.'.',
            ],
        ]);
        $this->assertSame([], $this->pivotRows($relation));
    }

    #[Test]
    #[DataProvider('updatingOperations')]
    public function it_gives_the_update_rules_to_a_pivot_row_that_update_sync_and_toggle_create(string $relation, string $operation): void
    {
        $response = $this->mutate($relation, $this->entry($relation, $operation, ['position' => 4]));

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$this->parent($relation)->getKey()]]);
        $this->assertSame([['position' => 4, 'weight' => null, 'added_by' => null, 'note' => null, 'created_at' => self::NOW]], $this->pivotRows($relation));
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_refuses_a_pivot_on_detach(string $relation): void
    {
        $this->attach($relation, ['position' => 1, 'weight' => 1]);

        $response = $this->mutate($relation, $this->entry($relation, 'detach', ['position' => 2]));

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.'.$relation.'.0.pivot' => ['The '.self::shown($relation).'.pivot field is prohibited when mutate.0.relations.'.$relation.'.0.operation is detach.'],
        ]);
        $this->assertSame([['position' => 1, 'weight' => 1, 'added_by' => null, 'note' => null, 'created_at' => self::NOW]], $this->pivotRows($relation));
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_refuses_a_pivot_that_is_not_an_object_with_one_message(string $relation): void
    {
        $response = $this->mutate($relation, $this->entry($relation, 'attach', 'heavy'));

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.'.$relation.'.0.pivot' => ['The '.self::shown($relation).'.pivot field must be an array.'],
        ]);
        $this->assertSame([], $this->pivotRows($relation));
    }
}
