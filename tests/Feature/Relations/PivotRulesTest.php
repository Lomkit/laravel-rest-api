<?php

namespace Lomkit\Rest\Tests\Feature\Relations;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\ConditionalPivotTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PivotRulesTest extends WeightedPivotTestCase
{
    #[Test]
    #[DataProvider('creatingOperations')]
    public function it_applies_the_creation_rules_of_a_pivot_field_on_attach_and_create(string $relation, string $operation): void
    {
        $response = $this->mutate($relation, $this->entry($relation, $operation, ['position' => 1]));

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.'.$relation.'.0.pivot.weight' => ['The '.self::shown($relation).'.pivot.weight field is required.'],
        ]);
        $this->assertSame([], $this->pivotRows($relation));
    }

    #[Test]
    #[DataProvider('creatingOperations')]
    public function it_applies_the_creation_rules_of_a_pivot_field_when_the_pivot_is_missing(string $relation, string $operation): void
    {
        $entry = $this->entry($relation, $operation, []);
        unset($entry['pivot']);

        $response = $this->mutate($relation, $entry);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.'.$relation.'.0.pivot.weight' => ['The '.self::shown($relation).'.pivot.weight field is required.'],
        ]);
        $this->assertSame([], $this->pivotRows($relation));
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_validates_each_pivot_with_the_operation_of_its_own_entry(string $relation): void
    {
        $response = $this->mutate(
            $relation,
            $this->entry($relation, 'update', ['position' => 1]),
            $this->entry($relation, 'create', ['position' => 2]),
        );

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.'.$relation.'.1.pivot.weight' => ['The '.self::shown($relation, 1).'.pivot.weight field is required.'],
        ]);
        $this->assertSame([], $this->pivotRows($relation));
    }

    #[Test]
    #[DataProvider('writingOperations')]
    public function it_validates_a_pivot_value_with_the_base_rule_of_its_type_on_every_writing_operation(string $relation, string $operation): void
    {
        $response = $this->mutate($relation, $this->entry($relation, $operation, ['weight' => 'heavy']));

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.'.$relation.'.0.pivot.weight' => ['The '.self::shown($relation).'.pivot.weight field must be an integer.'],
        ]);
        $this->assertSame([], $this->pivotRows($relation));
    }

    #[Test]
    #[DataProvider('updatingOperations')]
    public function it_applies_the_rules_of_a_pivot_field_on_update_sync_and_toggle(string $relation, string $operation): void
    {
        $response = $this->mutate($relation, $this->entry($relation, $operation, ['weight' => -1]));

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.'.$relation.'.0.pivot.weight' => ['The '.self::shown($relation).'.pivot.weight field must be at least 0.'],
        ]);
        $this->assertSame([], $this->pivotRows($relation));
    }

    #[Test]
    #[DataProvider('creatingOperations')]
    public function it_compares_a_rule_naming_another_pivot_key_with_the_pivot_of_the_same_entry(string $relation, string $operation): void
    {
        $response = $this->mutate($relation, $this->entry($relation, $operation, ['weight' => 1, 'note' => 'Seen twice']));

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.'.$relation.'.0.pivot.added_by' => ['The '.self::shown($relation).'.pivot.added_by field is required when '.self::shown($relation).'.pivot.note is present.'],
        ]);
        $this->assertSame([], $this->pivotRows($relation));
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_accepts_a_null_value_for_a_nullable_pivot_field(string $relation): void
    {
        $response = $this->mutate($relation, $this->entry($relation, 'attach', ['position' => null, 'weight' => 0]));

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$this->parent($relation)->getKey()]]);
        $this->assertSame([['position' => null, 'weight' => 0, 'added_by' => null, 'note' => null, 'created_at' => self::NOW]], $this->pivotRows($relation));
    }

    #[Test]
    public function it_resolves_a_conditional_pivot_rule_against_the_pivot_of_its_entry(): void
    {
        Rest::resource('conditional-pivot-tickets', ConditionalPivotTicketController::class)->register();
        $network = Tag::create(['name' => 'Network']);

        $response = $this->postJson('/conditional-pivot-tickets/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->jam->id,
            'relations' => ['tags' => [
                ['operation' => 'attach', 'key' => $this->hardware->id, 'pivot' => ['position' => 1]],
                ['operation' => 'attach', 'key' => $network->id, 'pivot' => ['position' => 2]],
            ]],
        ]]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The mutate.0.relations.tags.0.pivot.weight field is required.',
            'errors' => ['mutate.0.relations.tags.0.pivot.weight' => ['The mutate.0.relations.tags.0.pivot.weight field is required.']],
        ]);
        $tags = $this->jam->tags()
            ->pluck('tags.id')
            ->all();
        $this->assertSame([], $tags);
    }
}
