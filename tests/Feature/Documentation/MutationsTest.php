<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Documentation\Stubs\LabelController;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;
use Lomkit\Rest\Tests\Fixtures\Festival\Stage;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

class MutationsTest extends DocumentationTestCase
{
    #[Test]
    public function it_documents_each_entry_of_a_mutation_by_operation(): void
    {
        $document = $this->document();
        $schemas = $document['components']['schemas'];

        $this->assertSame(
            '{"type":"object","properties":{"mutate":{"type":"array","items":{"$ref":"#\/components\/schemas\/ShowMutation"},"minItems":1,"maxItems":100},"response":{"$ref":"#\\/components\\/schemas\\/ShowMutationResponse"},"confirmation":{"type":"string","description":"The token of the 428 answer of this endpoint, sent back with the same body to write"}},"required":["mutate"]}',
            json_encode($document['paths']['/shows/mutate']['post']['requestBody']['content']['application/json']['schema']),
        );
        $this->assertSame(
            '{"description":"An entry of `mutate`: `create` writes a new row, `update` changes the row of `key`.","discriminator":{"propertyName":"operation","mapping":{"create":"#\\/components\\/schemas\\/ShowMutationCreate","update":"#\\/components\\/schemas\\/ShowMutationUpdate"}},"oneOf":[{"$ref":"#\\/components\\/schemas\\/ShowMutationCreate"},{"$ref":"#\\/components\\/schemas\\/ShowMutationUpdate"}],"title":"ShowMutation"}',
            json_encode($schemas['ShowMutation']),
        );
        $this->assertSame(
            '{"type":"object","properties":{"operation":{"type":"string","const":"create"},"attributes":{"$ref":"#\\/components\\/schemas\\/ShowCreate"},"relations":{"$ref":"#\\/components\\/schemas\\/ShowRelations"}},"required":["operation"],"additionalProperties":false,"title":"ShowMutationCreate"}',
            json_encode($schemas['ShowMutationCreate']),
        );
        $this->assertSame(
            '{"type":"object","properties":{"operation":{"type":"string","const":"update"},"key":{"type":"integer"},"version":{"type":"string","format":"date-time","description":"The version of the row that the client read: its `updated_at` as the search returns it. When a row has another version, the request answers 409 and writes nothing"},"attributes":{"$ref":"#\\/components\\/schemas\\/ShowUpdate"},"relations":{"$ref":"#\\/components\\/schemas\\/ShowRelations"}},"required":["operation","key"],"additionalProperties":false,"title":"ShowMutationUpdate"}',
            json_encode($schemas['ShowMutationUpdate']),
        );
        $this->assertSame('{"type":"string","examples":["main"]}', json_encode($schemas['StageMutationUpdate']['properties']['key']));
    }

    #[Test]
    public function it_documents_the_version_only_where_a_versioned_resource_is_written(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('labels', LabelController::class));
        $document = $this->document();
        $schemas = $document['components']['schemas'];

        $this->assertSame(['operation', 'key', 'attributes'], array_keys($schemas['LabelMutationUpdate']['properties']));
        $this->assertSame(['operation', 'key', 'attributes', 'relations'], array_keys($schemas['StageMutationUpdate']['properties']));
        $this->assertSame([200, 403, 404, 422], array_keys($document['paths']['/labels/mutate']['post']['responses']));
        $this->assertSame([200, 403, 404, 409, 422], array_keys($document['paths']['/stages/mutate']['post']['responses']));
    }

    #[Test]
    public function it_documents_the_fields_that_each_operation_writes_with_their_type_and_rules(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertSame(
            '{"type":"object","description":"The fields that `create` writes","properties":{"title":{"type":"string","description":"Rules: string, max:80, required","examples":["Opening night","Closing night"]},"summary":{"type":["string","null"],"description":"Rules: nullable, string, min:8"},"price":{"type":["number","null"],"description":"Rules: nullable, numeric","examples":[12.5],"x-lomkit-meta":{"unit":"EUR"}},"seats":{"type":"integer","description":"Rules: integer, min:0"},"featured":{"type":"boolean","description":"Rules: boolean"},"starts_on":{"type":"string","format":"date","description":"Rules: date, required"},"starts_at":{"type":["string","null"],"format":"date-time","description":"Rules: nullable, date"},"status":{"type":"string","description":"Rules: enum, required","enum":["planned","on_sale"]},"level":{"type":"integer","description":"Rules: enum","enum":[1,2,3]},"settings":{"type":["object","null"],"description":"Rules: nullable, array, max_depth:64"},"secret":{"type":["string","null"],"description":"Rules: nullable, string"},"press_note":{"type":["string","null"],"description":"Rules: nullable, string"}},"additionalProperties":false,"title":"ShowCreate"}',
            json_encode($schemas['ShowCreate']),
        );
        $this->assertSame(array_keys($schemas['ShowCreate']['properties']), array_keys($schemas['ShowUpdate']['properties']));
        $this->assertSame(['planned', 'on_sale'], $schemas['ShowUpdate']['properties']['status']['enum']);
        $this->assertSame(['planned', 'on_sale', 'sold_out'], $schemas['Show']['properties']['status']['enum']);
        $this->assertSame('The fields that `update`, `sync` and `toggle` write', $schemas['ShowUpdate']['description']);
        $this->assertSame('Rules: string, max:80', $schemas['ShowUpdate']['properties']['title']['description']);
        $this->assertSame(['code', 'name'], array_keys($schemas['StageCreate']['properties']));
        $this->assertSame(['name'], array_keys($schemas['StageUpdate']['properties']));
    }

    #[Test]
    public function it_documents_an_input_only_field_as_written_and_never_returned(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertSame('{"type":["string","null"],"description":"Rules: nullable, string"}', json_encode($schemas['ShowCreate']['properties']['press_note']));
        $this->assertSame('{"type":["string","null"],"description":"Rules: nullable, string"}', json_encode($schemas['ShowUpdate']['properties']['press_note']));
        $this->assertArrayHasKey('title', $schemas['Show']['properties']);
        $this->assertArrayNotHasKey('press_note', $schemas['Show']['properties']);
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_documents_the_operations_of_each_relation_by_the_keys_that_they_take(string $component, string $schema): void
    {
        $this->assertSame($schema, json_encode($this->document()['components']['schemas'][$component]));
    }

    /** @return array<string, array{string, string}> */
    public static function relations(): array
    {
        return [
            'a relation to one' => ['ShowRelationMutationStage', '{"oneOf":[{"type":"object","properties":{"operation":{"type":"string","enum":["create"]},"attributes":{"$ref":"#\\/components\\/schemas\\/StageCreate"},"relations":{"$ref":"#\\/components\\/schemas\\/StageRelations"}},"required":["operation"],"additionalProperties":false},{"type":"object","properties":{"operation":{"type":"string","enum":["update"]},"key":{"type":"string","examples":["main"]},"attributes":{"$ref":"#\\/components\\/schemas\\/StageUpdate"},"relations":{"$ref":"#\\/components\\/schemas\\/StageRelations"}},"required":["operation","key"],"additionalProperties":false},{"type":"object","properties":{"operation":{"type":"string","enum":["attach","detach"]},"key":{"type":"string","examples":["main"]},"relations":{"$ref":"#\\/components\\/schemas\\/StageRelations"}},"required":["operation","key"],"additionalProperties":false}],"title":"ShowRelationMutationStage"}'],
            'a relation to many' => ['StageRelationMutationShows', '{"oneOf":[{"type":"object","properties":{"operation":{"type":"string","enum":["create"]},"attributes":{"$ref":"#\\/components\\/schemas\\/ShowCreate"},"relations":{"$ref":"#\\/components\\/schemas\\/ShowRelations"}},"required":["operation"],"additionalProperties":false},{"type":"object","properties":{"operation":{"type":"string","enum":["update"]},"key":{"description":"A key, or a list of keys: the operation applies to each of them","oneOf":[{"type":"integer"},{"type":"array","items":{"type":"integer"},"maxItems":100}]},"version":{"type":"string","format":"date-time","description":"The version of the row that the client read: its `updated_at` as the search returns it. When a row has another version, the request answers 409 and writes nothing"},"attributes":{"$ref":"#\\/components\\/schemas\\/ShowUpdate"},"relations":{"$ref":"#\\/components\\/schemas\\/ShowRelations"}},"required":["operation","key"],"additionalProperties":false},{"type":"object","properties":{"operation":{"type":"string","enum":["attach","detach"]},"key":{"description":"A key, or a list of keys: the operation applies to each of them","oneOf":[{"type":"integer"},{"type":"array","items":{"type":"integer"},"maxItems":100}]},"relations":{"$ref":"#\\/components\\/schemas\\/ShowRelations"}},"required":["operation","key"],"additionalProperties":false},{"type":"object","properties":{"operation":{"type":"string","enum":["sync","delete"]},"key":{"description":"A key, or a list of keys: the operation applies to each of them","oneOf":[{"type":"integer"},{"type":"array","items":{"type":"integer"},"maxItems":100}]}},"required":["operation","key"],"additionalProperties":false}],"title":"StageRelationMutationShows"}'],
            'a relation to many with pivot fields' => ['ShowRelationMutationArtists', '{"oneOf":[{"type":"object","properties":{"operation":{"type":"string","enum":["create"]},"attributes":{"$ref":"#\\/components\\/schemas\\/ArtistCreate"},"pivot":{"type":"object","description":"The pivot fields that the operation writes","properties":{"role":{"type":["string","null"],"description":"Rules: nullable, string"}},"additionalProperties":false}},"required":["operation"],"additionalProperties":false},{"type":"object","properties":{"operation":{"type":"string","enum":["update","toggle"]},"key":{"description":"A key, or a list of keys: the operation applies to each of them","oneOf":[{"type":"integer"},{"type":"array","items":{"type":"integer"},"maxItems":100}]},"attributes":{"$ref":"#\\/components\\/schemas\\/ArtistUpdate"},"pivot":{"type":"object","description":"The pivot fields that the operation writes","properties":{"role":{"type":["string","null"],"description":"Rules: nullable, string"}},"additionalProperties":false}},"required":["operation","key"],"additionalProperties":false},{"type":"object","properties":{"operation":{"type":"string","enum":["attach"]},"key":{"description":"A key, or a list of keys: the operation applies to each of them","oneOf":[{"type":"integer"},{"type":"array","items":{"type":"integer"},"maxItems":100}]},"pivot":{"type":"object","description":"The pivot fields that the operation writes","properties":{"role":{"type":["string","null"],"description":"Rules: nullable, string"}},"additionalProperties":false}},"required":["operation","key"],"additionalProperties":false},{"type":"object","properties":{"operation":{"type":"string","enum":["detach"]},"key":{"description":"A key, or a list of keys: the operation applies to each of them","oneOf":[{"type":"integer"},{"type":"array","items":{"type":"integer"},"maxItems":100}]}},"required":["operation","key"],"additionalProperties":false},{"type":"object","properties":{"operation":{"type":"string","enum":["sync"]},"key":{"description":"A key, or a list of keys: the operation applies to each of them","oneOf":[{"type":"integer"},{"type":"array","items":{"type":"integer"},"maxItems":100}]},"attributes":{"$ref":"#\\/components\\/schemas\\/ArtistUpdate"},"pivot":{"type":"object","description":"The pivot fields that the operation writes","properties":{"role":{"type":["string","null"],"description":"Rules: nullable, string"}},"additionalProperties":false},"without_detaching":{"type":"boolean","description":"When true, the rows that `key` does not list stay attached"}},"required":["operation","key"],"additionalProperties":false}],"title":"ShowRelationMutationArtists"}'],
        ];
    }

    #[Test]
    public function it_documents_an_entry_for_each_type_of_a_morph_to_relation(): void
    {
        $schemas = $this->document()['components']['schemas'];
        $show = $schemas['NoteRelationMutationNotableShow']['oneOf'];

        $this->assertSame('type', $schemas['NoteRelationMutationNotable']['discriminator']['propertyName']);
        $this->assertSame(
            [Show::class => '#/components/schemas/NoteRelationMutationNotableShow', Stage::class => '#/components/schemas/NoteRelationMutationNotableStage'],
            (array) $schemas['NoteRelationMutationNotable']['discriminator']['mapping'],
        );
        $this->assertSame([['create'], ['update'], ['attach', 'detach']], array_map(fn (array $branch): array => $branch['properties']['operation']['enum'], $show));
        $this->assertSame(['type' => 'string', 'const' => Show::class], $show[2]['properties']['type']);
        $this->assertSame([['operation', 'type'], ['operation', 'type', 'key'], ['operation', 'type', 'key']], array_column($show, 'required'));
        $this->assertSame(['type' => 'string', 'examples' => ['main']], $schemas['NoteRelationMutationNotableStage']['oneOf'][2]['properties']['key']);
    }

    #[Test]
    public function it_requires_the_relations_of_a_create_that_a_relation_requires_on_creation(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertSame(['operation', 'relations'], $schemas['NoteMutationCreate']['required']);
        $this->assertSame('#/components/schemas/NoteCreateRelations', $schemas['NoteMutationCreate']['properties']['relations']['$ref']);
        $this->assertSame(['notable'], $schemas['NoteCreateRelations']['required']);
        $this->assertSame(['operation', 'key'], $schemas['NoteMutationUpdate']['required']);
        $this->assertSame('#/components/schemas/NoteRelations', $schemas['NoteMutationUpdate']['properties']['relations']['$ref']);
        $this->assertSame(['operation'], $schemas['ShowMutationCreate']['required']);
    }

    #[Test]
    public function it_lists_the_relations_that_take_entries(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertSame(['stage', 'artists', 'comments', 'labels'], array_keys($schemas['ShowRelations']['properties']));
        $this->assertSame(['shows'], array_keys($schemas['StageRelations']['properties']));
        $this->assertSame(['notable'], array_keys($schemas['NoteRelations']['properties']));
        $this->assertSame(
            '{"type":"array","items":{"$ref":"#\/components\/schemas\/ShowRelationMutationArtists"},"maxItems":100}',
            json_encode($schemas['ShowRelations']['properties']['artists']),
        );
        $this->assertSame(['$ref' => '#/components/schemas/ShowRelationMutationStage'], $schemas['ShowRelations']['properties']['stage']);
        $this->assertSame('The operations on the related rows, by relation. Relations nest at most 3 deep: each `relations` inside an entry is one more level.', $schemas['ShowRelations']['description']);
    }

    #[Test]
    #[WithConfig('rest.limits.sizes.mutate', 7)]
    #[WithConfig('rest.limits.sizes.relation_entries', 5)]
    #[WithConfig('rest.limits.sizes.values', 3)]
    #[WithConfig('rest.limits.depths.mutations', 2)]
    public function it_takes_the_list_sizes_and_the_depth_of_a_mutation_from_the_configuration(): void
    {
        $document = $this->document();
        $schemas = $document['components']['schemas'];

        $this->assertSame(7, $document['paths']['/shows/mutate']['post']['requestBody']['content']['application/json']['schema']['properties']['mutate']['maxItems']);
        $this->assertSame(5, $schemas['ShowRelations']['properties']['artists']['maxItems']);
        $this->assertSame(3, $schemas['ShowRelationMutationArtists']['oneOf'][1]['properties']['key']['oneOf'][1]['maxItems']);
        $this->assertStringContainsString('Relations nest at most 2 deep', $schemas['ShowRelations']['description']);
    }

    /** @param array<string, mixed> $example */
    #[Test]
    #[DataProvider('examples')]
    public function it_gives_each_mutation_a_request_example_that_creates_a_row(string $path, array $example): void
    {
        $this->assertEquals(['default' => ['value' => $example]], $this->operation($path, 'post')['requestBody']['content']['application/json']['examples']);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function examples(): array
    {
        return [
            'a field without examples and a required relation' => ['/notes/mutate', ['mutate' => [['operation' => 'create', 'attributes' => ['body' => 'a'], 'relations' => ['notable' => ['operation' => 'attach', 'type' => Show::class, 'key' => 1]]]]]],
            'every type of field' => ['/shows/mutate', ['mutate' => [['operation' => 'create', 'attributes' => [
                'title' => 'Opening night',
                'summary' => 'aaaaaaaa',
                'price' => 12.5,
                'seats' => 1,
                'featured' => true,
                'starts_on' => '2026-01-01',
                'starts_at' => '2026-01-01T00:00:00Z',
                'status' => 'planned',
                'level' => 1,
                'settings' => new stdClass,
                'secret' => 'a',
                'press_note' => 'a',
            ]]]]],
            'a key that the client sends' => ['/stages/mutate', ['mutate' => [['operation' => 'create', 'attributes' => ['code' => 'main', 'name' => 'Main stage']]]]],
        ];
    }

    #[Test]
    public function it_documents_the_records_that_a_mutation_returns(): void
    {
        $document = $this->document();

        $this->assertSame(['$ref' => '#/components/schemas/ShowMutationResponse'], $document['paths']['/shows/mutate']['post']['requestBody']['content']['application/json']['schema']['properties']['response']);
        $this->assertSame(
            '{"type":"object","description":"The records to return after the mutation: one for each entry of `mutate`, at the same index, read and rendered as a search reads its rows. A response renders at most 10000 rows, included rows counted, from the limits of the request: the entries of `mutate`, and for each to-many include its limit for each parent row, nested includes multiplying. Over it, the request answers 422.","properties":{"selects":{"type":"array","items":{"$ref":"#\\/components\\/schemas\\/ShowSelect"},"maxItems":100},"aggregates":{"type":"array","items":{"$ref":"#\\/components\\/schemas\\/ShowAggregate"},"maxItems":20},"includes":{"type":"array","items":{"$ref":"#\\/components\\/schemas\\/ShowInclude"},"maxItems":20},"gates":{"type":"array","description":"The abilities to answer for each row, under `gates` in the row","items":{"type":"string","enum":["view","update","delete","restore","forceDelete","feature"]},"uniqueItems":true}},"additionalProperties":false,"title":"ShowMutationResponse"}',
            json_encode($document['components']['schemas']['ShowMutationResponse']),
        );
    }

    #[Test]
    public function it_documents_the_operations_and_the_records_of_the_result_of_a_mutation(): void
    {
        $document = $this->document();
        $schemas = $document['components']['schemas'];

        $this->assertSame(['$ref' => '#/components/schemas/ShowMutateResult'], $document['paths']['/shows/mutate']['post']['responses'][200]['content']['application/json']['schema']);
        $this->assertSame(
            '{"type":"object","description":"The operations that the mutation wrote","properties":{"operations":{"type":"array","description":"One entry for each entry of `mutate`, at the same index","items":{"$ref":"#\\/components\\/schemas\\/RestMutateOperation"}},"records":{"type":"array","description":"Present when the request sends `response`: one record for each entry of `mutate`, at the same index, or `null` for a row that the search of the resource does not return","items":{"anyOf":[{"$ref":"#\\/components\\/schemas\\/Show"},{"type":"null"}]}}},"required":["operations"],"title":"ShowMutateResult"}',
            json_encode($schemas['ShowMutateResult']),
        );
        $this->assertSame(
            '{"type":"object","description":"The operation of one entry and the key of its row","properties":{"operation":{"type":"string","enum":["create","update","attach","detach","sync","toggle","delete"]},"type":{"type":"string","description":"The morph type of the row, on an entry of a `MorphTo` relation"},"key":{"anyOf":[{"description":"The key of the row that a `create` wrote, else the key that the entry sent. An entry with a list of keys answers a list.","oneOf":[{"type":"integer"},{"type":"string"},{"type":"array","items":{"oneOf":[{"type":"integer"},{"type":"string"}]}}]},{"type":"null"}]},"relations":{"type":"object","description":"Present when the entry sends `relations`: the entries of each relation that it sends, an object for a relation to one, a list for a relation to many","additionalProperties":{"anyOf":[{"oneOf":[{"$ref":"#\\/components\\/schemas\\/RestMutateOperation"},{"type":"array","items":{"$ref":"#\\/components\\/schemas\\/RestMutateOperation"}}]},{"type":"null"}]}}},"required":["operation","key"],"title":"RestMutateOperation"}',
            json_encode($schemas['RestMutateOperation']),
        );
    }
}
