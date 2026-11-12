<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Dedoc\Scramble\Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ScrambleDetailsTest extends ScrambleTestCase
{
    private const string OPERATORS = '["=","!=",">",">=","<","<=","like","not like","ilike","not ilike","in","not in","between","not between","is null","is not null"]';

    private const string FIELD = '{"type":"object","properties":{"name":{"type":"string","enum":["id","title","summary","status","featured","views","rating","published_on","published_at","metadata"]},"label":{"type":["string","null"],"description":"The name of the field in validation messages, set with label(); null without one"},"type":{"type":"string"},"nullable":{"type":"boolean"},"select":{"type":"boolean","description":"With fields.capabilities: accepted in search.selects and returned"},"sort":{"type":"boolean","description":"With fields.capabilities: accepted in search.sorts"},"filter":{"type":"object","properties":{"operators":{"type":"array","description":"With fields.filter: accepted by search.filters on this field; empty when it is not filterable","items":{"type":"string","enum":'.self::OPERATORS.'}}},"required":["operators"]},"create":{"type":"boolean","description":"With fields.capabilities: writable on create"},"update":{"type":"boolean","description":"With fields.capabilities: writable on update"},"rules":{"type":"object","description":"Validation rules as strings: all on every write, create and update on that operation only","properties":{"all":{"type":"array","items":{"type":"string"}},"create":{"type":"array","items":{"type":"string"}},"update":{"type":"array","items":{"type":"string"}}},"required":["all","create","update"]},"meta":{"type":"object"},"values":{"type":"array","description":"Enum fields only: the backing values","items":{}},"offered":{"type":"array","description":"Enum fields that offer only some cases: the backing values that writes accept","items":{}}},"required":["name","label","type","nullable"]}';

    private const string PIVOT_FIELD = '{"type":"object","properties":{"name":{"type":"string"},"label":{"type":["string","null"],"description":"The name of the field in validation messages, set with label(); null without one"},"type":{"type":"string"},"nullable":{"type":"boolean"},"select":{"type":"boolean","description":"Returned in the pivot of an included row; false when the model method is not a many-to-many relation"},"sort":{"type":"boolean","description":"Always false: no endpoint sorts on a pivot column"},"filter":{"type":"object","properties":{"operators":{"type":"array","description":"Accepted by <relation>.pivot.<field> filters; empty when it is not filterable","items":{"type":"string","enum":'.self::OPERATORS.'}}},"required":["operators"]},"create":{"type":"boolean","description":"Writable on create and attach"},"update":{"type":"boolean","description":"Writable on update, sync and toggle"},"rules":{"type":"object","description":"Validation rules as strings: all on every write, create and update on that operation only","properties":{"all":{"type":"array","items":{"type":"string"}},"create":{"type":"array","items":{"type":"string"}},"update":{"type":"array","items":{"type":"string"}}},"required":["all","create","update"]},"meta":{"type":"object"},"values":{"type":"array","description":"Enum fields only: the backing values","items":{}},"offered":{"type":"array","description":"Enum fields that offer only some cases: the backing values that writes accept","items":{}}},"required":["name","label","type","nullable","select","sort","filter","create","update","rules","meta"]}';

    private const string INPUT = '{"type":"object","properties":{"name":{"type":"string"},"label":{"type":["string","null"],"description":"The name of the field in validation messages, set with label(); null without one"},"type":{"type":"string"},"nullable":{"type":"boolean"},"default":{},"rules":{"type":"array","items":{"type":"string"}},"meta":{"type":"object"},"values":{"type":"array","description":"Enum fields only: the backing values","items":{}},"offered":{"type":"array","description":"Enum fields that offer only some cases: the backing values that writes accept","items":{}}},"required":["name","label","type","nullable","default","rules","meta"]}';

    private const string RULES = '{"type":"object","description":"With rules: the rules of the resource, keyed by attribute, as strings","properties":{"all":{"type":"object","additionalProperties":{"type":"array","items":{"type":"string"}}},"create":{"type":"object","additionalProperties":{"type":"array","items":{"type":"string"}}},"update":{"type":"object","additionalProperties":{"type":"array","items":{"type":"string"}}}},"required":["all","create","update"]}';

    private const string TEXT = '{"type":["object","null"],"description":"With text: the text search of the resource; null without one","properties":{"fields":{"type":"array","description":"The searchable fields; empty with ScoutEngine, whose index decides what the text matches","items":{"type":"string"}},"modes":{"type":"array","items":{"type":"string","enum":["words","prefix","phrase","websearch"]}},"relevance":{"type":"boolean","description":"The @relevance sort is accepted with a text"},"trashed":{"type":"boolean","description":"search.trashed can be sent with a text"},"max_length":{"type":"integer"},"engine":{"type":["object","null"],"description":"The grammar of a search with a filled text on a search engine, which replaces the sort and filter of the field entries: filters combined with and only, no scopes, @relevance alone and desc only; null on the database","properties":{"filters":{"type":"array","items":{"type":"object","properties":{"name":{"type":"string"},"type":{"type":"string"},"operators":{"type":"array","items":{"type":"string","enum":'.self::OPERATORS.'}},"values":{"type":"array","description":"Enum attributes only: the backing values","items":{}}},"required":["name","type","operators"]}},"sorts":{"type":"array","items":{"type":"string"}},"max_sorts":{"type":["integer","null"],"description":"The most sorts in one search; null when unbounded, 0 when only @relevance is accepted"},"max_page_size":{"type":["integer","null"]},"window":{"type":["integer","null"],"description":"page times limit must not exceed it; null when unbounded"}},"required":["filters","sorts","max_sorts","max_page_size","window"]}},"required":["fields","modes","relevance","trashed","max_length","engine"]}';

    #[Test]
    public function it_describes_the_details_response(): void
    {
        $operation = $this->operation('/posts', 'get');

        $this->assertSame('Get Post details', $operation['summary']);
        $this->assertSame('The fields, limits and meta of the resource, then the sections and parts that the with parameter asks for', $operation['responses'][200]['description']);
        $this->assertSame('{"$ref":"#\/components\/schemas\/PostDetails"}', json_encode($operation['responses'][200]['content']['application/json']['schema']));
        $this->assertSame(['data'], $this->detailsSchema('/posts')['required']);
    }

    #[Test]
    public function it_documents_the_with_parameter(): void
    {
        $values = 'fields.capabilities, fields.filter, fields.rules, fields.meta, relations, relations.constraints, relations.operations, relations.pivot_fields, actions, actions.fields, actions.authorized, actions.meta, metrics, metrics.authorized, metrics.meta, instructions, instructions.fields, instructions.meta, scopes, scopes.fields, scopes.meta, rules, text, gates, confirmations, authorizations';

        $this->assertSame([[
            'name' => 'with',
            'in' => 'query',
            'description' => 'The sections and parts to add to the fields, limits and meta of the response, separated by commas. A part adds its section. Accepted values: '.$values.'.',
            'schema' => ['type' => 'string'],
            'example' => 'fields.capabilities,fields.filter,scopes.fields,actions.authorized',
        ]], $this->operation('/posts', 'get')['parameters']);
    }

    #[Test]
    public function it_documents_the_field_entries(): void
    {
        $this->assertSame(self::FIELD, json_encode($this->details()['fields']['items']));
    }

    #[Test]
    public function it_documents_the_relation_entries(): void
    {
        $constraints = '{"type":"object","description":"With relations.constraints","properties":{"required_on_creation":{"type":"boolean"},"prohibited_on_creation":{"type":"boolean"},"required_on_update":{"type":"boolean"},"prohibited_on_update":{"type":"boolean"}},"required":["required_on_creation","prohibited_on_creation","required_on_update","prohibited_on_update"]}';
        $pivotFields = '{"type":"array","description":"With relations.pivot_fields, on many-to-many relations only: the pivot fields of the relation","items":'.self::PIVOT_FIELD.'}';
        $types = '{"type":"object","description":"MorphTo only: the resource class of each morph type","additionalProperties":{"type":"string"}}';
        $morph = '{"type":"object","description":"MorphTo only: the columns of the relation","properties":{"type_column":{"type":"string"},"key_column":{"type":"string"}},"required":["type_column","key_column"]}';
        $operations = '{"type":"array","description":"With relations.operations: the operations that the entries of the relation accept in mutate; empty when the relation takes no entry","items":{"type":"string","enum":["create","update","attach","detach","sync","toggle","delete"]}}';
        $syncRemoves = '{"type":["string","null"],"description":"With relations.operations, on HasMany and MorphMany relations only: what a `sync` entry does to the children that it does not list, set with syncable(); null when the relation has no such sync","enum":["detach","delete",null]}';

        $this->assertSame(
            '{"type":"object","properties":{"resource":{"type":"string","description":"The related resource class; absent on a MorphTo relation with several types"},"relation":{"type":"string","enum":["author"]},"constraints":'.$constraints.',"operations":'.$operations.',"sync_removes":'.$syncRemoves.',"name":{"type":"string"},"pivot_fields":'.$pivotFields.',"types":'.$types.',"morph":'.$morph.'},"required":["relation","name"]}',
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
        $this->assertSame('{"type":"array","description":"With gates: the abilities that search.gates accepts, the built-in ones first, then those that the resource declares; empty when the resource turns its gates off","items":{"type":"string","enum":["view","create","update","delete"]}}', json_encode($this->details()['gates']));
    }

    #[Test]
    public function it_documents_restore_and_force_delete_for_a_soft_deletable_model(): void
    {
        $this->assertSame('{"type":"string","enum":["view","create","update","delete","restore","forceDelete"]}', json_encode($this->detailsSchema('/authors')['properties']['data']['properties']['gates']['items']));
    }

    #[Test]
    public function it_documents_the_answers_to_the_class_level_abilities(): void
    {
        $this->assertSame('{"type":"object","description":"With authorizations: the answers to the class-level abilities that the routes of the resource check, for the user of the request, keyed by ability: viewAny, create, then those of the endpoints, the actions and the metrics","additionalProperties":{"type":"object","properties":{"allowed":{"type":"boolean"},"message":{"type":["string","null"]}},"required":["allowed","message"]}}', json_encode($this->details()['authorizations']));
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
        $fields = fn (string $section): string => '"fields":{"type":"array","description":"With '.$section.'.fields","items":'.self::INPUT.'},"meta":{"type":"object","description":"With '.$section.'.meta"}';

        return [
            'actions' => ['actions', '{"type":"object","properties":{"name":{"type":"string"},"uriKey":{"type":"string","enum":["publish-posts","queued-publish-posts","batched-publish-posts","change-status"]},"target":{"type":"string","enum":["search","resources","none"]},'.$fields('actions').',"destructive":{"type":"boolean","description":"True when the action is declared with destructive(); a hint for clients, the endpoint runs the action the same way"},"confirm":{"type":["string","null"],"description":"The text set with confirm(), for clients to show before they run the action; null without one"},"confirmation":{"type":"boolean","description":"True when the action declares confirmation(): the endpoint can answer 428 with a signed token, and runs the action when the same body comes back with that token"},"authorized":{"type":"boolean","description":"With actions.authorized: true when the user passes the abilities that the action route checks before it reads the request"}},"required":["name","uriKey","target","destructive","confirm","confirmation"]}'],
            'metrics' => ['metrics', '{"type":"object","properties":{"name":{"type":"string"},"uriKey":{"type":"string"},"type":{"type":"string","description":"The type of the result, given again as data.type in the response of the metric"},"ranges":{"type":"array","description":"The values that the range key of the request accepts; the first one applies when the request sends none","items":{"type":"object","properties":{"value":{"type":"integer"},"label":{"type":"string"}},"required":["value","label"]}},"meta":{"type":"object","description":"With metrics.meta"},"authorized":{"type":"boolean","description":"With metrics.authorized: true when the user passes the abilities that the metric route checks before it reads the request"}},"required":["name","uriKey","type","ranges"]}'],
            'instructions' => ['instructions', '{"type":"object","properties":{"name":{"type":"string"},"uriKey":{"type":"string","enum":["highlight-posts","in-status"]},'.$fields('instructions').',"text":{"type":"boolean","description":"Can be sent with search.text on this resource"}},"required":["name","uriKey","text"]}'],
            'scopes' => ['scopes', '{"type":"object","properties":{"name":{"type":"string","enum":["publishedSince","popular","viewedBetween","featured","reaching"]},'.$fields('scopes').'},"required":["name"]}'],
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
    public function it_documents_the_details_of_each_resource_in_its_own_component(): void
    {
        $schemas = app(Generator::class)()['components']['schemas'] ?? [];

        $this->assertSame(['AuthorDetails', 'CategoryDetails', 'PostDetails', 'ProductDetails', 'StaffProductDetails'], array_values(array_filter(array_keys($schemas), fn (string $name): bool => str_ends_with($name, 'Details'))));
    }

    /** @return array<string, mixed> */
    private function details(): array
    {
        return $this->detailsSchema('/posts')['properties']['data']['properties'];
    }
}
