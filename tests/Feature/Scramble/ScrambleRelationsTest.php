<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\ReadOnlyTicketsTeamController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\KeptTicketController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\OnlyTicketController;
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
        $schemas = $this->schemas();

        $this->assertSame(['team', 'tags', 'labels'], array_keys((array) $schemas['TicketInclude']['discriminator']['mapping']));
        $this->assertSame(['team', 'tags', 'labels'], array_keys($schemas['TicketRelations']['properties']));
    }

    #[Test]
    public function it_documents_a_relation_to_one_as_an_entry_and_a_relation_to_many_as_a_list(): void
    {
        $schemas = $this->schemas();
        $products = $schemas['CategoryRelations']['properties']['products'];

        $this->assertSame(['$ref' => '#/components/schemas/ProductRelationMutationCategory'], $schemas['ProductRelations']['properties']['category']);
        $this->assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CategoryRelationMutationProducts'], 'maxItems' => 100], $products);
        $this->assertSame(['type' => 'integer'], $schemas['ProductRelationMutationCategory']['oneOf'][1]['properties']['key']);
        $this->assertSame(
            [['type' => 'integer'], ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 100]],
            $schemas['CategoryRelationMutationProducts']['oneOf'][1]['properties']['key']['oneOf'],
        );
        $this->assertSame('boolean', $schemas['CategoryRelationMutationFeaturedProducts']['oneOf'][3]['properties']['without_detaching']['type']);
    }

    #[Test]
    #[DataProvider('pivotRelations')]
    public function it_documents_the_pivot_fields_writable_on_each_operation_with_their_rules(string $component): void
    {
        Route::prefix('api')->group(function (): void {
            Rest::resource('weighted-tickets', WeightedTicketController::class)->register();
            Rest::resource('weighted-tags', WeightedTagController::class)->register();
        });

        $branches = $this->schemas()[$component]['oneOf'];
        $create = [
            'type' => 'object',
            'description' => 'The pivot fields that the operation writes',
            'properties' => [
                'position' => ['type' => ['integer', 'null'], 'description' => 'Rules: nullable, integer'],
                'weight' => ['type' => 'integer', 'description' => 'Rules: integer, min:0, required'],
                'added_by' => ['type' => 'string', 'description' => 'Rules: string, required_with:note'],
                'note' => ['type' => 'string', 'description' => 'Rules: string'],
            ],
            'additionalProperties' => false,
        ];

        $this->assertSame([['create'], ['update', 'toggle'], ['attach'], ['detach'], ['sync']], array_map(fn (array $branch): array => $branch['properties']['operation']['enum'], $branches));
        $this->assertSame($create, $branches[0]['properties']['pivot']);
        $this->assertSame($create, $branches[2]['properties']['pivot']);
        $this->assertSame(['position', 'weight', 'note'], array_keys($branches[1]['properties']['pivot']['properties']));
        $this->assertSame('Rules: integer, min:0', $branches[4]['properties']['pivot']['properties']['weight']['description']);
        $this->assertArrayNotHasKey('pivot', $branches[3]['properties']);
    }

    /** @return array<string, array{string}> */
    public static function pivotRelations(): array
    {
        return [
            'belongs to many' => ['WeightedTicketRelationMutationTags'],
            'morph to many' => ['WeightedTicketRelationMutationLabels'],
            'morphed by many' => ['WeightedTagRelationMutationLabelledTickets'],
        ];
    }

    #[Test]
    public function it_documents_the_operations_and_the_pivot_of_a_custom_relation(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('curated-tickets', CuratedTicketController::class)->register());

        $tags = $this->schemas()['CuratedTicketRelationMutationTags']['oneOf'];

        $this->assertSame([['attach'], ['detach']], array_map(fn (array $branch): array => $branch['properties']['operation']['enum'], $tags));
        $this->assertSame([
            'type' => 'object',
            'description' => 'The pivot fields that the operation writes',
            'properties' => ['position' => ['type' => ['integer', 'null'], 'description' => 'Rules: nullable, integer']],
            'additionalProperties' => false,
        ], $tags[0]['properties']['pivot']);
    }

    #[Test]
    public function it_documents_the_operations_left_on_a_relation_and_a_sync_that_keeps_the_rows(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('kept-tickets', KeptTicketController::class)->register());

        $schemas = $this->schemas();
        $operations = fn (string $component): array => array_map(fn (array $branch): array => $branch['properties']['operation']['enum'], $schemas[$component]['oneOf']);
        $tags = $schemas['KeptTicketRelationMutationTags']['oneOf'];
        $labels = $schemas['KeptTicketRelationMutationLabels']['oneOf'];

        $this->assertSame([['create'], ['update'], ['attach']], $operations('KeptTicketRelationMutationTeam'));
        $this->assertSame([['create'], ['update'], ['attach'], ['sync']], $operations('KeptTicketRelationMutationTags'));
        $this->assertSame([['create'], ['attach', 'toggle'], ['detach'], ['sync']], $operations('KeptTicketRelationMutationLabels'));
        $this->assertSame(
            ['type' => 'boolean', 'description' => 'Must be true: the relation does not detach rows, so the rows that `key` does not list stay attached', 'const' => true],
            $tags[3]['properties']['without_detaching'],
        );
        $this->assertSame(['operation', 'key', 'without_detaching'], $tags[3]['required']);
        $this->assertSame(['type' => 'boolean', 'description' => 'When true, the rows that `key` does not list stay attached'], $labels[3]['properties']['without_detaching']);
        $this->assertSame(['operation', 'key'], $labels[3]['required']);
        $this->assertSame([['attributes', 'pivot', 'relations'], ['key', 'pivot'], ['key'], ['key', 'pivot', 'without_detaching']], array_map(fn (array $branch): array => array_values(array_diff(array_keys($branch['properties']), ['operation'])), $labels));
    }

    #[Test]
    public function it_documents_the_operations_that_a_relation_keeps(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('only-tickets', OnlyTicketController::class)->register());

        $schemas = $this->schemas();
        $tags = $schemas['OnlyTicketRelationMutationTags']['oneOf'];

        $this->assertSame(['team', 'tags'], array_keys($schemas['OnlyTicketRelations']['properties']));
        $this->assertSame(['attach'], $schemas['OnlyTicketRelationMutationTeam']['properties']['operation']['enum']);
        $this->assertSame([['attach'], ['sync']], array_map(fn (array $branch): array => $branch['properties']['operation']['enum'], $tags));
        $this->assertTrue($tags[1]['properties']['without_detaching']['const']);
        $this->assertSame(['operation', 'key', 'without_detaching'], $tags[1]['required']);
    }

    #[Test]
    public function it_leaves_out_a_relation_that_accepts_no_operation(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('read-only-tickets-teams', ReadOnlyTicketsTeamController::class)->register());

        $schemas = $this->schemas();

        $this->assertArrayHasKey('ReadOnlyTicketsTeamMutationCreate', $schemas);
        $this->assertArrayNotHasKey('ReadOnlyTicketsTeamRelations', $schemas);
        $this->assertArrayNotHasKey('ReadOnlyTicketsTeamRelationMutationTickets', $schemas);
        $this->assertArrayNotHasKey('TeamRelationMutationReplies', $schemas);
    }

    #[Test]
    public function it_documents_the_type_of_a_morph_to_entry_and_the_types_of_a_morph_to_include(): void
    {
        $schemas = $this->schemas();
        $types = $schemas['LabelIncludeLabelable']['properties']['types'];

        $this->assertSame('type', $schemas['LabelRelationMutationLabelable']['discriminator']['propertyName']);
        $this->assertSame([Book::class, Shelf::class], array_keys((array) $schemas['LabelRelationMutationLabelable']['discriminator']['mapping']));
        $this->assertSame(['type' => 'string', 'const' => Book::class], $schemas['LabelRelationMutationLabelableBook']['oneOf'][0]['properties']['type']);
        $this->assertSame([Book::class, Shelf::class, null], $schemas['LabelCreate']['properties']['labelable_type']['enum']);
        $this->assertSame([Book::class, Shelf::class], array_keys($types['properties']));
        $this->assertSame('The search of each type of related row, keyed by morph type', $types['description']);
    }

    #[Test]
    public function it_documents_an_optional_type_on_a_morph_to_entry_of_one_type(): void
    {
        $owner = $this->schemas()['ArtworkRelationMutationOwner']['oneOf'];

        $this->assertSame(['type' => 'string', 'description' => 'The morph type of the related row', 'enum' => [Collector::class]], $owner[0]['properties']['type']);
        $this->assertSame([['operation'], ['operation', 'key'], ['operation', 'key']], array_column($owner, 'required'));
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
