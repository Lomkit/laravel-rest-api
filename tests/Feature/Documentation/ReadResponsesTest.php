<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Documentation\Stubs\KeyedStageController;
use Lomkit\Rest\Tests\Feature\Documentation\Stubs\KeyedStageResource;
use Lomkit\Rest\Tests\Feature\Documentation\Stubs\OtherStageController;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ReadResponsesTest extends DocumentationTestCase
{
    private const string GATE = '{"$ref":"#\/components\/schemas\/RestGateAnswer"}';

    #[Test]
    public function it_documents_a_record_a_page_and_changed_rows_for_each_resource(): void
    {
        $this->assertSame(
            ['Artist', 'Comment', 'Label', 'Note', 'NoteDetails', 'NotePaginated', 'NoteRows', 'RestGateAnswer', 'Show', 'ShowDetails', 'ShowPaginated', 'ShowRows', 'ShowWithType', 'Stage', 'StageDetails', 'StagePaginated', 'StageRows', 'StageWithType'],
            array_values(preg_grep('/^(Rest[A-Z]\w+|[A-Z][a-z]+(Details|Paginated|Rows|WithType)?)$/', array_keys($this->document()['components']['schemas']))),
        );
    }

    #[Test]
    public function it_documents_the_selectable_fields_the_gates_and_the_relations_of_a_record(): void
    {
        $this->assertSame(
            ['id', 'title', 'summary', 'price', 'seats', 'featured', 'starts_on', 'starts_at', 'status', 'level', 'settings', 'gates', 'stage', 'artists', 'comments', 'labels'],
            array_keys($this->document()['components']['schemas']['Show']['properties']),
        );
    }

    #[Test]
    public function it_documents_a_morph_to_relation_as_one_of_its_targets_with_their_type(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertSame(
            '{"anyOf":[{"description":"Present when the search includes the relation","discriminator":{"propertyName":"@type","mapping":{"Lomkit\\\\Rest\\\\Tests\\\\Fixtures\\\\Festival\\\\Show":"#\/components\/schemas\/ShowWithType","Lomkit\\\\Rest\\\\Tests\\\\Fixtures\\\\Festival\\\\Stage":"#\/components\/schemas\/StageWithType"}},"oneOf":[{"$ref":"#\/components\/schemas\/ShowWithType"},{"$ref":"#\/components\/schemas\/StageWithType"}]},{"type":"null"}]}',
            json_encode($schemas['Note']['properties']['notable']),
        );
        $this->assertSame(
            '{"allOf":[{"$ref":"#\/components\/schemas\/Stage"},{"type":"object","properties":{"@type":{"type":"string","const":"Lomkit\\\\Rest\\\\Tests\\\\Fixtures\\\\Festival\\\\Stage"}},"required":["@type"]}],"title":"StageWithType"}',
            json_encode($schemas['StageWithType']),
        );
    }

    #[Test]
    public function it_documents_the_related_rows_of_each_relation_family(): void
    {
        $show = $this->document()['components']['schemas']['Show']['properties'];

        $this->assertSame('{"anyOf":[{"description":"Present when the search includes the relation","$ref":"#\/components\/schemas\/Stage"},{"type":"null"}]}', json_encode($show['stage']));
        $this->assertSame('{"type":"array","description":"Present when the search includes the relation","items":{"$ref":"#\/components\/schemas\/Comment"}}', json_encode($show['comments']));
        $this->assertSame(
            '{"type":"array","description":"Present when the search includes the relation","items":{"allOf":[{"$ref":"#\/components\/schemas\/Artist"},{"type":"object","properties":{"pivot":{"type":"object","properties":{"role":{"type":["string","null"]}}}}}]}}',
            json_encode($show['artists']),
        );
    }

    #[Test]
    #[WithConfig('rest.gates.names.authorized_to_view', 'can_view')]
    public function it_documents_the_gates_of_a_row_and_of_a_page_under_their_names(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertSame(
            '{"type":"object","description":"Present when the search lists gates: the answer to each gate that it lists","properties":{"can_view":'.self::GATE.',"authorized_to_update":'.self::GATE.',"authorized_to_delete":'.self::GATE.',"authorized_to_restore":'.self::GATE.',"authorized_to_force_delete":'.self::GATE.',"authorized_to_feature":'.self::GATE.'}}',
            json_encode($schemas['Show']['properties']['gates']),
        );
        $this->assertSame(
            '{"type":"object","properties":{"gates":{"type":"object","description":"Present when the search lists the create gate","properties":{"authorized_to_create":'.self::GATE.'}}}}',
            json_encode($schemas['ShowPaginated']['properties']['meta']),
        );
        $this->assertSame('{"type":"object","properties":{"allowed":{"type":"boolean"},"message":{"type":["string","null"]}},"required":["allowed","message"],"title":"RestGateAnswer"}', json_encode($schemas['RestGateAnswer']));
    }

    #[Test]
    public function it_documents_the_envelope_of_a_page_and_of_changed_rows(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertSame(
            ['current_page', 'data', 'from', 'last_page', 'per_page', 'to', 'total', 'meta'],
            $schemas['StagePaginated']['required'],
        );
        $this->assertSame(
            '{"type":"object","description":"The rows that the request changed","properties":{"data":{"type":"array","items":{"$ref":"#\/components\/schemas\/Stage"}},"meta":{"type":"object"}},"required":["data","meta"],"title":"StageRows"}',
            json_encode($schemas['StageRows']),
        );
    }

    #[Test]
    #[DataProvider('readOperations')]
    public function it_documents_the_response_of_each_read_operation(string $path, string $method, string $response): void
    {
        $this->assertSame($response, json_encode($this->operation($path, $method)['responses'][200]));
    }

    /** @return array<string, array{string, string, string}> */
    public static function readOperations(): array
    {
        return [
            'search' => ['/shows/search', 'post', '{"description":"A page of rows","content":{"application\/json":{"schema":{"$ref":"#\/components\/schemas\/ShowPaginated"}}}}'],
            'delete' => ['/shows', 'delete', '{"description":"The deleted rows","content":{"application\/json":{"schema":{"$ref":"#\/components\/schemas\/ShowRows"}}}}'],
            'restore' => ['/shows/restore', 'post', '{"description":"The restored rows","content":{"application\/json":{"schema":{"$ref":"#\/components\/schemas\/ShowRows"}}}}'],
            'force delete' => ['/shows/force', 'delete', '{"description":"The deleted rows","content":{"application\/json":{"schema":{"$ref":"#\/components\/schemas\/ShowRows"}}}}'],
        ];
    }

    #[Test]
    #[DataProvider('keyedOperations')]
    public function it_types_the_keys_of_each_delete_operation(string $path, string $method, string $body): void
    {
        $this->assertSame($body, json_encode($this->operation($path, $method)['requestBody']));
    }

    /** @return array<string, array{string, string, string}> */
    public static function keyedOperations(): array
    {
        $integers = '{"required":true,"content":{"application\/json":{"schema":{"type":"object","properties":{"resources":{"type":"array","items":{"type":"integer"},"minItems":1,"maxItems":1000,"uniqueItems":true}},"required":["resources"]},"examples":{"default":{"value":{"resources":[1]}}}}}}';

        return [
            'delete with an integer key' => ['/shows', 'delete', $integers],
            'restore' => ['/shows/restore', 'post', $integers],
            'force delete' => ['/shows/force', 'delete', $integers],
            'delete with a string key and its example' => ['/stages', 'delete', '{"required":true,"content":{"application\/json":{"schema":{"type":"object","properties":{"resources":{"type":"array","items":{"type":"string","examples":["main"]},"minItems":1,"maxItems":1000,"uniqueItems":true}},"required":["resources"]},"examples":{"default":{"value":{"resources":["main"]}}}}}}'],
        ];
    }

    #[Test]
    #[DataProvider('keyTypes')]
    public function it_gives_each_key_type_an_example_without_a_declared_one(string $keyType, string $items, string $example): void
    {
        KeyedStageResource::$keyType = $keyType;
        Route::prefix('api')->group(fn () => Rest::resource('keyed-stages', KeyedStageController::class));

        $content = $this->operation('/keyed-stages', 'delete')['requestBody']['content']['application/json'];

        $this->assertSame($items, json_encode($content['schema']['properties']['resources']['items']));
        $this->assertSame($example, json_encode($content['examples']['default']['value']));
    }

    /** @return array<string, array{string, string, string}> */
    public static function keyTypes(): array
    {
        return [
            'string' => ['string', '{"type":"string"}', '{"resources":["a"]}'],
            'uuid' => ['uuid', '{"type":"string","format":"uuid"}', '{"resources":["00000000-0000-0000-0000-000000000000"]}'],
            'ulid' => ['ulid', '{"type":"string"}', '{"resources":["00000000000000000000000000"]}'],
        ];
    }

    #[Test]
    public function it_qualifies_the_name_of_a_resource_whose_name_another_resource_took(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('other-stages', OtherStageController::class));

        $document = $this->document();

        $this->assertSame('{"$ref":"#\/components\/schemas\/Lomkit.Rest.Tests.Feature.Documentation.Stubs.StagePaginated"}', json_encode($document['paths']['/other-stages/search']['post']['responses'][200]['content']['application/json']['schema']));
        $this->assertSame('{"$ref":"#\/components\/schemas\/Lomkit.Rest.Tests.Feature.Documentation.Stubs.Stage"}', json_encode($document['components']['schemas']['Lomkit.Rest.Tests.Feature.Documentation.Stubs.StageRows']['properties']['data']['items']));
        $this->assertSame('{"$ref":"#\/components\/schemas\/Stage"}', json_encode($document['components']['schemas']['StageRows']['properties']['data']['items']));
    }

    #[Test]
    #[WithConfig('rest.limits.sizes.resources', 25)]
    public function it_takes_the_most_keys_of_a_request_from_the_configuration(): void
    {
        $this->assertSame(25, $this->operation('/shows', 'delete')['requestBody']['content']['application/json']['schema']['properties']['resources']['maxItems']);
    }
}
