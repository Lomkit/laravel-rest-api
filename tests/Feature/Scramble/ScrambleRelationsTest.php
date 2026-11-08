<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\WeightedTagController;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\WeightedTicketController;
use Lomkit\Rest\Tests\Feature\Scramble\Stubs\CuratedTicketController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Gallery\Collector;
use Lomkit\Rest\Tests\Fixtures\Gallery\Gallery;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ScrambleRelationsTest extends ScrambleTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog, new Gallery, new Helpdesk, new Library];
    }

    #[Test]
    public function it_documents_the_declared_relations_and_not_the_fields_of_their_pivot(): void
    {
        $this->assertSame(['team', 'tags', 'labels'], array_keys((array) $this->schemas()['TicketInclude']['discriminator']['mapping']));
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

    #[Test]
    public function it_documents_the_type_of_a_morph_to_entry_and_the_types_of_a_morph_to_include(): void
    {
        $labelable = $this->mutateEntry('/labels')['relations']['properties']['labelable'];
        $types = $this->schemas()['LabelIncludeLabelable']['properties']['types'];

        $this->assertSame(['operation', 'key', 'attributes', 'type'], array_keys($labelable['properties']));
        $this->assertSame(['type' => 'string', 'description' => 'The morph type of the related record; required when the relation has several types', 'enum' => [Book::class, Shelf::class]], $labelable['properties']['type']);
        $this->assertSame([Book::class, Shelf::class], array_keys($types['properties']));
        $this->assertSame('The search of each type of related row, keyed by morph type', $types['description']);
    }

    #[Test]
    public function it_documents_the_search_of_a_morph_to_relation_of_one_type_in_place_or_by_type(): void
    {
        $schemas = $this->schemas();

        $this->assertSame(['relation', 'alias', 'filters', 'gates', 'selects', 'types'], array_keys($schemas['ArtworkIncludeOwner']['properties']));
        $this->assertSame([Collector::class], array_keys($schemas['ArtworkIncludeOwner']['properties']['types']['properties']));
        $this->assertSame(['relation', 'exists', 'type', 'nested', 'types'], array_keys($schemas['ArtworkRelationFilterOwner']['properties']));
    }
}
