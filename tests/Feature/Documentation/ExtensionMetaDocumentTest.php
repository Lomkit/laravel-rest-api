<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Lomkit\Rest\Exceptions\InvalidDocumentationExtensionException;
use Lomkit\Rest\Exceptions\InvalidMetaExtensionException;
use Lomkit\Rest\Extensions\Kinds\AddsDetailsMeta;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\QuotaStageMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RegionStageMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RegionStageMetaNamedArea;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RegionStageMetaNamedInstructions;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SchemaOnlyMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SearchOnlyMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SharedSchemaMeta;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

#[WithConfig('rest.extensions', [QuotaStageMeta::class, RegionStageMeta::class])]
class ExtensionMetaDocumentTest extends DocumentationTestCase
{
    private const string GATE = '{"$ref":"#\/components\/schemas\/RestGateAnswer"}';

    private const string PAGE_META = '{"type":"object","properties":{"gates":{"type":"object","description":"Present when the search lists the create gate","properties":{"authorized_to_create":'.self::GATE.'}},"quota":{"type":"object","description":"Added by the [quota] extension for the page","properties":{"remaining":{"type":"integer"}},"required":["remaining"],"additionalProperties":false},"region":{"type":"object","description":"Added by the [region] extension for the page","additionalProperties":{}}},"required":["quota","region"],"additionalProperties":false}';

    private const string DETAILS_META = '{"type":"object","description":"The keys that extensions add, each under the name of the extension, for the user of the request","properties":{"quota":{"type":"object","description":"Added by the [quota] extension for the user of the request","properties":{"plan":{"type":"string"}},"required":["plan"],"additionalProperties":false},"region":{"type":"object","description":"Added by the [region] extension for the user of the request","additionalProperties":{}}},"required":["quota","region"],"additionalProperties":false}';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        SharedSchemaMeta::$schema = null;
    }

    #[\Override]
    protected function tearDown(): void
    {
        SharedSchemaMeta::$schema = null;

        parent::tearDown();
    }

    #[Test]
    public function it_lists_one_property_per_extension_in_the_closed_meta_of_a_page(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertSame(self::PAGE_META, json_encode($schemas['StagePaginated']['properties']['meta']));
    }

    #[Test]
    public function it_adds_the_extension_properties_to_the_open_meta_of_a_resource_with_search_meta(): void
    {
        $meta = $this->document()['components']['schemas']['ShowPaginated']['properties']['meta'];

        $this->assertSame(['gates', 'instructions', 'quota'], array_keys($meta['properties']));
        $this->assertSame(['quota'], $meta['required']);
        $this->assertSame('Also holds the keys that searchMeta() of the resource returns for the page', $meta['description']);
        $this->assertEquals(new stdClass, $meta['additionalProperties']);
        $this->assertSame('{"type":"object","description":"Added by the [quota] extension for the page","properties":{"remaining":{"type":"integer"}},"required":["remaining"],"additionalProperties":false}', json_encode($meta['properties']['quota']));
    }

    #[Test]
    public function it_lists_one_property_per_extension_in_the_details_meta(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertSame(self::DETAILS_META, json_encode($schemas['StageDetails']['properties']['data']['properties']['meta']));
        $this->assertSame(
            '{"type":"object","description":"The keys that detailsMeta() of the resource returns for the user of the request, then the keys that extensions add, each under the name of the extension","properties":{"quota":{"type":"object","description":"Added by the [quota] extension for the user of the request","properties":{"plan":{"type":"string"}},"required":["plan"],"additionalProperties":false}},"required":["quota"],"additionalProperties":{}}',
            json_encode($schemas['ShowDetails']['properties']['data']['properties']['meta']),
        );
    }

    #[Test]
    public function it_keeps_the_meta_of_a_resource_that_no_meta_extension_reaches(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertSame('{"type":"object","description":"Empty: the resource adds no keys with detailsMeta()","additionalProperties":false}', json_encode($schemas['NoteDetails']['properties']['data']['properties']['meta']));
        $this->assertSame('{"type":"object","properties":{"gates":{"type":"object","description":"Present when the search lists the create gate","properties":{"authorized_to_create":'.self::GATE.'}}},"additionalProperties":false}', json_encode($schemas['NotePaginated']['properties']['meta']));
    }

    #[Test]
    public function it_lists_the_extension_properties_in_name_order_whatever_the_class_and_config_order(): void
    {
        $keys = [];
        $documents = [];

        foreach ([[QuotaStageMeta::class, RegionStageMetaNamedArea::class], [RegionStageMetaNamedArea::class, QuotaStageMeta::class]] as $extensions) {
            config(['rest.extensions' => $extensions]);
            $document = $this->document();
            $schemas = $document['components']['schemas'];
            $keys[] = array_keys($schemas['StagePaginated']['properties']['meta']['properties']);
            $keys[] = array_keys($schemas['StageDetails']['properties']['data']['properties']['meta']['properties']);
            $documents[] = json_encode($document);
        }

        $this->assertSame([['gates', 'area', 'quota'], ['area', 'quota'], ['gates', 'area', 'quota'], ['area', 'quota']], $keys);
        $this->assertSame($documents[0], $documents[1]);
    }

    #[Test]
    public function it_describes_a_schema_that_an_extension_shares_between_both_meta(): void
    {
        config(['rest.extensions' => [SharedSchemaMeta::class]]);

        $schemas = $this->document()['components']['schemas'];

        $this->assertSame('Added by the [shared] extension for the page', $schemas['StagePaginated']['properties']['meta']['properties']['shared']['description']);
        $this->assertSame('Added by the [shared] extension for the user of the request', $schemas['StageDetails']['properties']['data']['properties']['meta']['properties']['shared']['description']);
        $this->assertSame('', SharedSchemaMeta::$schema?->description);
    }

    /** @return array<string, array{string, class-string, class-string}> */
    public static function metaWithoutKind(): array
    {
        return [
            'a search meta schema without any meta kind' => ['schema', SchemaOnlyMeta::class, AddsSearchMeta::class],
            'a details meta schema of an extension that adds only search meta' => ['search', SearchOnlyMeta::class, AddsDetailsMeta::class],
        ];
    }

    /**
     * @param  class-string  $class
     * @param  class-string  $kind
     */
    #[Test]
    #[DataProvider('metaWithoutKind')]
    public function it_refuses_a_meta_schema_of_an_extension_without_the_meta_kind(string $name, string $class, string $kind): void
    {
        config(['rest.extensions' => [$class], 'app.debug' => false]);

        $this->expectExceptionObject(InvalidDocumentationExtensionException::metaWithoutKind($name, $class, StageResource::class, $kind));

        $this->document();
    }

    #[Test]
    public function it_names_the_registered_class_of_a_meta_schema_without_the_meta_kind_bound_to_a_subclass(): void
    {
        $bound = new class extends SchemaOnlyMeta {};
        $this->app->bind(SchemaOnlyMeta::class, static fn (): SchemaOnlyMeta => $bound);
        config(['rest.extensions' => [SchemaOnlyMeta::class], 'app.debug' => false]);
        $expected = InvalidDocumentationExtensionException::metaWithoutKind('schema', SchemaOnlyMeta::class, StageResource::class, AddsSearchMeta::class)->getMessage();
        $exception = null;

        try {
            $this->document();
        } catch (InvalidDocumentationExtensionException $caught) {
            $exception = $caught;
        }

        $this->assertSame([$expected, true, false], [$exception?->getMessage(), $exception?->concerns(SchemaOnlyMeta::class), $exception?->concerns($bound::class)]);
    }

    #[Test]
    public function it_refuses_an_extension_named_after_a_key_of_the_package_in_the_document(): void
    {
        config(['rest.extensions' => [RegionStageMetaNamedInstructions::class], 'app.debug' => false]);

        $this->expectExceptionObject(InvalidMetaExtensionException::instructionsName('instructions', RegionStageMetaNamedInstructions::class, StageResource::class));

        $this->document();
    }

    #[Test]
    public function it_documents_a_valid_openapi_document_with_extension_meta(): void
    {
        $this->assertSame([], (new OpenApiValidator)->documentErrors($this->document()));
    }
}
