<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Dedoc\Scramble\Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ScrambleDetailsTest extends ScrambleTestCase
{
    private const string OPERATORS = '["=","!=",">",">=","<","<=","like","not like","ilike","not ilike","in","not in","between","not between","is null","is not null"]';

    private const string FIELD = '{"type":"object","properties":{"name":{"type":"string"},"label":{"type":["string","null"],"description":"The name of the field in validation messages, set with label(); null without one"},"type":{"type":"string"},"nullable":{"type":"boolean"},"select":{"type":"boolean","description":"Accepted in search.selects and returned"},"sort":{"type":"boolean","description":"Accepted in search.sorts"},"filter":{"type":"object","properties":{"operators":{"type":"array","description":"Accepted by search.filters on this field; empty when it is not filterable","items":{"type":"string","enum":'.self::OPERATORS.'}}},"required":["operators"]},"create":{"type":"boolean","description":"Writable on create"},"update":{"type":"boolean","description":"Writable on update"},"rules":{"type":"object","description":"Validation rules as strings: all on every write, create and update on that operation only","properties":{"all":{"type":"array","items":{"type":"string"}},"create":{"type":"array","items":{"type":"string"}},"update":{"type":"array","items":{"type":"string"}}},"required":["all","create","update"]},"meta":{"type":"object"},"values":{"type":"array","description":"Enum fields only: the backing values","items":{}}},"required":["name","label","type","nullable","select","sort","filter","create","update","rules","meta"]}';

    private const string PIVOT_FIELD = '{"type":"object","properties":{"name":{"type":"string"},"label":{"type":["string","null"],"description":"The name of the field in validation messages, set with label(); null without one"},"type":{"type":"string"},"nullable":{"type":"boolean"},"select":{"type":"boolean","description":"Returned in the pivot of an included row; false when the model method is not a many-to-many relation"},"sort":{"type":"boolean","description":"Always false: no endpoint sorts on a pivot column"},"filter":{"type":"object","properties":{"operators":{"type":"array","description":"Accepted by <relation>.pivot.<field> filters; empty when it is not filterable","items":{"type":"string","enum":'.self::OPERATORS.'}}},"required":["operators"]},"create":{"type":"boolean","description":"Writable on create and attach"},"update":{"type":"boolean","description":"Writable on update, sync and toggle"},"rules":{"type":"object","description":"Validation rules as strings: all on every write, create and update on that operation only","properties":{"all":{"type":"array","items":{"type":"string"}},"create":{"type":"array","items":{"type":"string"}},"update":{"type":"array","items":{"type":"string"}}},"required":["all","create","update"]},"meta":{"type":"object"},"values":{"type":"array","description":"Enum fields only: the backing values","items":{}}},"required":["name","label","type","nullable","select","sort","filter","create","update","rules","meta"]}';

    private const string INPUT = '{"type":"object","properties":{"name":{"type":"string"},"label":{"type":["string","null"],"description":"The name of the field in validation messages, set with label(); null without one"},"type":{"type":"string"},"nullable":{"type":"boolean"},"default":{},"rules":{"type":"array","items":{"type":"string"}},"meta":{"type":"object"},"values":{"type":"array","description":"Enum fields only: the backing values","items":{}}},"required":["name","label","type","nullable","default","rules","meta"]}';

    private const string RULES = '{"type":"object","description":"The rules of the resource, keyed by attribute, as strings","properties":{"all":{"type":"object","additionalProperties":{"type":"array","items":{"type":"string"}}},"create":{"type":"object","additionalProperties":{"type":"array","items":{"type":"string"}}},"update":{"type":"object","additionalProperties":{"type":"array","items":{"type":"string"}}}},"required":["all","create","update"]}';

    private const string TEXT = '{"type":["object","null"],"description":"The text search of the resource; null without one","properties":{"fields":{"type":"array","description":"The searchable fields; empty with ScoutEngine, whose index decides what the text matches","items":{"type":"string"}},"modes":{"type":"array","items":{"type":"string","enum":["words","prefix","phrase","websearch"]}},"relevance":{"type":"boolean","description":"The @relevance sort is accepted with a text"},"trashed":{"type":"boolean","description":"search.trashed can be sent with a text"},"max_length":{"type":"integer"},"engine":{"type":["object","null"],"description":"The grammar of a search with a filled text on a search engine, which replaces the sort and filter of the field entries: filters combined with and only, no scopes, @relevance alone and desc only; null on the database","properties":{"filters":{"type":"array","items":{"type":"object","properties":{"name":{"type":"string"},"type":{"type":"string"},"operators":{"type":"array","items":{"type":"string","enum":'.self::OPERATORS.'}},"values":{"type":"array","description":"Enum attributes only: the backing values","items":{}}},"required":["name","type","operators"]}},"sorts":{"type":"array","items":{"type":"string"}},"max_sorts":{"type":["integer","null"],"description":"The most sorts in one search; null when unbounded, 0 when only @relevance is accepted"},"max_page_size":{"type":["integer","null"]},"window":{"type":["integer","null"],"description":"page times limit must not exceed it; null when unbounded"}},"required":["filters","sorts","max_sorts","max_page_size","window"]}},"required":["fields","modes","relevance","trashed","max_length","engine"]}';

    #[Test]
    public function it_describes_the_details_response(): void
    {
        $operation = $this->operation('/posts', 'get');

        $this->assertSame('Get Post details', $operation['summary']);
        $this->assertSame('The fields, relations, scopes, actions, instructions, rules, text search and gates of the resource', $operation['responses'][200]['description']);
        $this->assertSame(['data'], $operation['responses'][200]['content']['application/json']['schema']['required']);
    }

    #[Test]
    public function it_documents_the_field_entries(): void
    {
        $this->assertSame(self::FIELD, json_encode($this->details()['fields']['items']));
    }

    #[Test]
    public function it_documents_the_relation_entries(): void
    {
        $constraints = '{"type":"object","properties":{"required_on_creation":{"type":"boolean"},"prohibited_on_creation":{"type":"boolean"},"required_on_update":{"type":"boolean"},"prohibited_on_update":{"type":"boolean"}},"required":["required_on_creation","prohibited_on_creation","required_on_update","prohibited_on_update"]}';
        $pivotFields = '{"type":"array","description":"Many-to-many relations only: the pivot fields of the relation","items":'.self::PIVOT_FIELD.'}';
        $types = '{"type":"object","description":"MorphTo only: the resource class of each morph type","additionalProperties":{"type":"string"}}';
        $morph = '{"type":"object","description":"MorphTo only: the columns of the relation","properties":{"type_column":{"type":"string"},"key_column":{"type":"string"}},"required":["type_column","key_column"]}';

        $this->assertSame(
            '{"type":"object","properties":{"resource":{"type":"string"},"relation":{"type":"string"},"constraints":'.$constraints.',"name":{"type":"string"},"pivot_fields":'.$pivotFields.',"types":'.$types.',"morph":'.$morph.'},"required":["resource","relation","constraints","name"]}',
            json_encode($this->details()['relations']['items']),
        );
    }

    #[Test]
    public function it_documents_the_rules_of_the_resource(): void
    {
        $this->assertSame(self::RULES, json_encode($this->details()['rules']));
    }

    #[Test]
    public function it_documents_the_text_search_of_the_resource(): void
    {
        $this->assertSame(self::TEXT, json_encode($this->details()['text']));
    }

    #[Test]
    public function it_documents_the_gates_of_the_resource(): void
    {
        $this->assertSame('{"type":"array","description":"The abilities that search.gates accepts: the built-in ones, then those that the resource declares; empty when the resource turns its gates off","items":{"type":"string"}}', json_encode($this->details()['gates']));
    }

    #[Test]
    #[DataProvider('declarations')]
    public function it_documents_the_input_entries_of_each_declaration(string $declaration, string $entry): void
    {
        $this->assertSame($entry, json_encode($this->details()[$declaration]['items']));
    }

    /** @return array<string, array{string, string}> */
    public static function declarations(): array
    {
        $fields = '"fields":{"type":"array","items":'.self::INPUT.'},"meta":{"type":"object"}';

        return [
            'actions' => ['actions', '{"type":"object","properties":{"name":{"type":"string"},"uriKey":{"type":"string"},'.$fields.',"standalone":{"type":"boolean"},"targeted":{"type":"boolean"}},"required":["name","uriKey","fields","meta","standalone","targeted"]}'],
            'instructions' => ['instructions', '{"type":"object","properties":{"name":{"type":"string"},"uriKey":{"type":"string"},'.$fields.',"text":{"type":"boolean","description":"Can be sent with search.text on this resource"}},"required":["name","uriKey","fields","meta","text"]}'],
            'scopes' => ['scopes', '{"type":"object","properties":{"name":{"type":"string"},'.$fields.'},"required":["name","fields","meta"]}'],
        ];
    }

    #[Test]
    public function it_documents_the_text_flag_of_the_instructions_and_the_limits(): void
    {
        $details = $this->details();

        $this->assertSame('{"type":"boolean","description":"Can be sent with search.text on this resource"}', json_encode($details['instructions']['items']['properties']['text']));
        $this->assertSame('{"type":"array","items":{"type":"integer"}}', json_encode($details['limits']));
    }

    #[Test]
    public function it_adds_no_schema_component_for_the_details_response(): void
    {
        $schemas = app(Generator::class)()['components']['schemas'] ?? [];

        $this->assertSame([], array_values(array_diff(array_keys($schemas), ['LengthAwarePaginator'])));
    }

    /** @return array<string, mixed> */
    private function details(): array
    {
        return $this->operation('/posts', 'get')['responses'][200]['content']['application/json']['schema']['properties']['data']['properties'];
    }
}
