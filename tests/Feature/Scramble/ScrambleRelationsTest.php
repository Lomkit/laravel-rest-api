<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\WeightedTagController;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\WeightedTicketController;
use Lomkit\Rest\Tests\Feature\Scramble\Stubs\CuratedTicketController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ScrambleRelationsTest extends ScrambleTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog, new Helpdesk];
    }

    #[Test]
    public function it_documents_the_declared_relations_and_not_the_fields_of_their_pivot(): void
    {
        $relation = $this->body($this->operation('/tickets/search', 'post'))['properties']['search']['properties']['includes']['items']['properties']['relation'];

        $this->assertSame(['team', 'tags', 'labels'], $relation['enum']);
        $this->assertSame("**Available relations:**\n\n- `team` (BelongsTo)\n- `tags` (BelongsToMany)\n- `labels` (MorphToMany)", $relation['description']);
        $this->assertSame(['team', 'tags', 'labels'], array_keys($this->mutateEntry('/tickets')['relations']['properties']));
    }

    #[Test]
    public function it_documents_a_relation_to_one_as_an_entry_and_a_relation_to_many_as_a_list(): void
    {
        $category = $this->mutateEntry('/products')['relations']['properties']['category'];
        $relations = $this->mutateEntry('/categories')['relations']['properties'];

        $this->assertSame(['object', 'Type: BelongsTo'], [$category['type'], $category['description']]);
        $this->assertSame(['operation', 'key', 'attributes'], array_keys($category['properties']));
        $this->assertArrayNotHasKey('type', (array) $category['properties']['key']);
        $this->assertSame(['array', 'Type: HasMany'], [$relations['products']['type'], $relations['products']['description']]);
        $this->assertSame(['operation', 'key', 'attributes'], array_keys($relations['products']['items']['properties']));
        $this->assertSame(['array', 'Type: BelongsToMany'], [$relations['featuredProducts']['type'], $relations['featuredProducts']['description']]);
        $this->assertSame(['operation', 'key', 'attributes', 'pivot', 'without_detaching'], array_keys($relations['featuredProducts']['items']['properties']));
        $this->assertSame('boolean', $relations['featuredProducts']['items']['properties']['without_detaching']['type']);
    }

    #[Test]
    #[DataProvider('pivotRelations')]
    public function it_documents_the_pivot_fields_writable_on_each_operation_with_their_rules(string $path, string $relation): void
    {
        Route::prefix('api')->group(function (): void {
            Rest::resource('weighted-tickets', WeightedTicketController::class)->register();
            Rest::resource('weighted-tags', WeightedTagController::class)->register();
        });

        $this->assertSame([
            'type' => 'object',
            'description' => 'Writable on create and attach: position, weight, added_by, note. Writable on update, sync and toggle: position, weight, note. Writable on detach: none.',
            'properties' => [
                'position' => ['type' => 'integer', 'description' => 'create and attach: nullable, integer | update, sync and toggle: nullable, integer'],
                'weight' => ['type' => 'integer', 'description' => 'create and attach: integer, min:0, required | update, sync and toggle: integer, min:0'],
                'added_by' => ['type' => 'string', 'description' => 'create and attach: string, required_with:note'],
                'note' => ['type' => 'string', 'description' => 'create and attach: string | update, sync and toggle: string'],
            ],
        ], $this->mutateEntry($path)['relations']['properties'][$relation]['items']['properties']['pivot']);
    }

    /** @return array<string, array{string, string}> */
    public static function pivotRelations(): array
    {
        return [
            'belongs to many' => ['/weighted-tickets', 'tags'],
            'morph to many' => ['/weighted-tickets', 'labels'],
            'morphed by many' => ['/weighted-tags', 'labelledTickets'],
        ];
    }

    #[Test]
    public function it_documents_the_operations_and_the_pivot_of_a_custom_relation(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('curated-tickets', CuratedTicketController::class)->register());

        $tags = $this->mutateEntry('/curated-tickets')['relations']['properties']['tags'];

        $this->assertSame(['array', 'Type: CuratedTags'], [$tags['type'], $tags['description']]);
        $this->assertSame(['attach', 'detach'], $tags['items']['properties']['operation']['enum']);
        $this->assertSame([
            'type' => 'object',
            'description' => 'Writable on attach: position. Writable on detach: none.',
            'properties' => ['position' => ['type' => 'integer', 'description' => 'attach: nullable, integer']],
        ], $tags['items']['properties']['pivot']);
    }
}
