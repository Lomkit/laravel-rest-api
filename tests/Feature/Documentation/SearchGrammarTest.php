<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Dedoc\Scramble\Support\Generator\Components;
use Illuminate\Http\Request;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Search\SearchSchema;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;
use Lomkit\Rest\Tests\Fixtures\Festival\Stage;
use Lomkit\Rest\Validation\Search\SearchShape;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SearchGrammarTest extends DocumentationTestCase
{
    private const string ALIAS = '"pattern":"^[A-Za-z_][A-Za-z0-9_]*$","maxLength":255}';

    #[Test]
    public function it_documents_the_search_of_each_resource_in_its_own_closed_component(): void
    {
        $document = $this->document();
        $schemas = $document['components']['schemas'];

        $this->assertSame('{"type":"object","properties":{"search":{"$ref":"#\/components\/schemas\/ShowSearch"}}}', json_encode($this->operation('/shows/search', 'post')['requestBody']['content']['application/json']['schema']));
        $this->assertSame(['text', 'trashed', 'filters', 'gates', 'scopes', 'sorts', 'selects', 'aggregates', 'includes', 'instructions', 'limit', 'page'], array_keys($schemas['ShowSearch']['properties']));
        $this->assertSame(['filters', 'gates', 'sorts', 'selects', 'aggregates', 'includes', 'limit', 'page'], array_keys($schemas['StageSearch']['properties']));
        $this->assertSame(['filters', 'gates', 'sorts', 'selects', 'includes', 'limit', 'page'], array_keys($schemas['NoteSearch']['properties']));
        $this->assertFalse($schemas['ShowSearch']['additionalProperties']);
    }

    #[Test]
    public function it_documents_the_sorts_the_selects_the_gates_the_limit_and_the_page_of_a_search(): void
    {
        $schemas = $this->document()['components']['schemas'];
        $search = $schemas['ShowSearch']['properties'];

        $this->assertSame(
            '{"type":"object","description":"The key of an aggregate of the same search is accepted as `field` too, and is not listed.","properties":{"field":{"type":"string","enum":["id","title","summary","price","seats","featured","starts_on","starts_at","status","level","stage.code","stage.name"]},"direction":{"type":"string","enum":["asc","desc"]}},"required":["field"],"additionalProperties":false,"title":"ShowSort"}',
            json_encode($schemas['ShowSort']),
        );
        $this->assertSame('{"type":"object","properties":{"field":{"type":"string","enum":["id","title","summary","price","seats","featured","starts_on","starts_at","status","level","settings"]}},"required":["field"],"additionalProperties":false,"title":"ShowSelect"}', json_encode($schemas['ShowSelect']));
        $this->assertSame('{"type":"array","description":"The abilities to answer for each row, under `gates` in the row, and for `create` under `gates` in `meta`","items":{"type":"string","enum":["view","create","update","delete","restore","forceDelete","feature"]},"uniqueItems":true}', json_encode($search['gates']));
        $this->assertSame('{"type":"integer","enum":[10,25,50]}', json_encode($search['limit']));
        $this->assertSame('{"type":"integer","minimum":1}', json_encode($search['page']));
        $this->assertSame(['scopes' => 10, 'sorts' => 20, 'selects' => 100, 'aggregates' => 20, 'includes' => 20, 'instructions' => 10], array_map(fn (array $list): int => $list['maxItems'], array_intersect_key($search, array_flip(['sorts', 'selects', 'scopes', 'aggregates', 'includes', 'instructions']))));
    }

    #[Test]
    public function it_documents_an_include_for_each_relation(): void
    {
        $this->assertSame(
            '{"description":"Related rows to add to each row. Includes nest at most 3 deep: each `includes` inside an include is one more level.","discriminator":{"propertyName":"relation","mapping":{"stage":"#\/components\/schemas\/ShowIncludeStage","artists":"#\/components\/schemas\/ShowIncludeArtists","comments":"#\/components\/schemas\/ShowIncludeComments","labels":"#\/components\/schemas\/ShowIncludeLabels"}},"oneOf":[{"$ref":"#\/components\/schemas\/ShowIncludeStage"},{"$ref":"#\/components\/schemas\/ShowIncludeArtists"},{"$ref":"#\/components\/schemas\/ShowIncludeComments"},{"$ref":"#\/components\/schemas\/ShowIncludeLabels"}],"title":"ShowInclude"}',
            json_encode($this->document()['components']['schemas']['ShowInclude']),
        );
    }

    #[Test]
    public function it_documents_the_search_of_an_include_by_relation_family(): void
    {
        $schemas = $this->document()['components']['schemas'];
        $notable = $schemas['NoteIncludeNotable']['properties'];

        $this->assertSame(
            '{"type":"object","properties":{"relation":{"type":"string","const":"stage"},"alias":{"type":"string","description":"The key of the related rows in each row, in place of the name of the relation. It lets a search include one relation several times.",'.self::ALIAS.',"filters":{"type":"array","items":{"$ref":"#\/components\/schemas\/StageFilter"},"maxItems":100},"gates":{"type":"array","description":"The abilities to answer for each row, under `gates` in the row","items":{"type":"string","enum":["view","update","delete","restore","forceDelete"]},"uniqueItems":true},"selects":{"type":"array","items":{"$ref":"#\/components\/schemas\/StageSelect"},"maxItems":100},"aggregates":{"type":"array","items":{"$ref":"#\/components\/schemas\/StageAggregate"},"maxItems":20},"includes":{"type":"array","items":{"$ref":"#\/components\/schemas\/StageInclude"},"maxItems":20}},"required":["relation"],"additionalProperties":false,"title":"ShowIncludeStage"}',
            json_encode($schemas['ShowIncludeStage']),
        );
        $this->assertSame(['relation', 'alias', 'filters', 'gates', 'sorts', 'selects', 'aggregates', 'includes', 'limit'], array_keys($schemas['ShowIncludeComments']['properties']));
        $this->assertSame(['$ref' => '#/components/schemas/CommentSort'], $schemas['ShowIncludeComments']['properties']['sorts']['items']);
        $this->assertSame(['relation', 'alias', 'types'], array_keys($notable));
        $this->assertSame([Show::class, Stage::class], array_keys($notable['types']['properties']));
        $this->assertSame(['filters', 'gates', 'scopes', 'selects', 'aggregates', 'includes', 'instructions'], array_keys($notable['types']['properties'][Show::class]['properties']));
        $this->assertFalse($notable['types']['properties'][Show::class]['additionalProperties']);
    }

    #[Test]
    public function it_documents_the_aggregates_of_each_relation_by_function(): void
    {
        $schemas = $this->document()['components']['schemas'];
        $branch = fn (string $types, string $field): string => '{"type":"object","properties":{"relation":{"type":"string","const":"comments"},"type":{"type":"string","enum":['.$types.']},'.$field.'"alias":{"type":"string","description":"The key of the value in each row; by default the relation, the type and the field in snake case, such as `comments_max_rating`.",'.self::ALIAS.',"filters":{"type":"array","items":{"$ref":"#\/components\/schemas\/CommentFilter"},"maxItems":100}},"required":["relation","type"'.($field === '' ? '' : ',"field"').'],"additionalProperties":false}';

        $this->assertSame(['stage', 'artists', 'comments', 'labels'], array_keys((array) $schemas['ShowAggregate']['discriminator']['mapping']));
        $this->assertSame(
            '{"oneOf":['.$branch('"count","exists"', '').','.$branch('"min","max"', '"field":{"type":"string","enum":["id","rating","body"]},').','.$branch('"avg","sum"', '"field":{"type":"string","enum":["rating"]},').'],"title":"ShowAggregateComments"}',
            json_encode($schemas['ShowAggregateComments']),
        );
    }

    #[Test]
    #[DataProvider('inputs')]
    public function it_documents_the_parameters_of_each_scope_and_the_fields_of_each_instruction(string $component, string $schema): void
    {
        $this->assertSame($schema, json_encode($this->document()['components']['schemas'][$component]));
    }

    /** @return array<string, array{string, string}> */
    public static function inputs(): array
    {
        return [
            'the scopes' => ['ShowScope', '{"discriminator":{"propertyName":"name","mapping":{"startingFrom":"#\/components\/schemas\/ShowScopeStartingFrom"}},"oneOf":[{"$ref":"#\/components\/schemas\/ShowScopeStartingFrom"}],"title":"ShowScope"}'],
            'a scope with a required and a default parameter' => ['ShowScopeStartingFrom', '{"type":"object","properties":{"name":{"type":"string","const":"startingFrom"},"parameters":{"type":"array","description":"In this order: from, min_seats","prefixItems":[{"type":"string","format":"date"},{"type":"integer"}],"minItems":1,"maxItems":2}},"required":["name","parameters"],"additionalProperties":false,"title":"ShowScopeStartingFrom"}'],
            'an instruction with a default and a nullable field' => ['ShowInstructionOnlyFeatured', '{"type":"object","properties":{"name":{"type":"string","const":"only-featured"},"fields":{"type":"array","items":{"oneOf":[{"type":"object","properties":{"name":{"type":"string","const":"featured"},"value":{"type":"boolean"}},"required":["name"],"additionalProperties":false},{"type":"object","properties":{"name":{"type":"string","const":"min_seats"},"value":{"type":["integer","null"]}},"required":["name"],"additionalProperties":false}]},"maxItems":100}},"required":["name"],"additionalProperties":false,"title":"ShowInstructionOnlyFeatured"}'],
        ];
    }

    #[Test]
    public function it_documents_the_search_of_an_action_and_of_a_metric_from_the_same_pieces(): void
    {
        $components = new Components;
        $searches = new SearchSchema(new ComponentRegistry($components));
        $resource = (new RestContext(RestRequest::createFrom(Request::create('/api/shows/search', 'POST')), $this->app))->resource(ShowResource::class);

        $this->assertSame('#/components/schemas/ShowActionSearch', $searches->reference($resource, SearchShape::Rows)->getUri());
        $this->assertSame('#/components/schemas/ShowMetricSearch', $searches->reference($resource, SearchShape::Aggregate)->getUri());
        $this->assertSame(['text', 'trashed', 'filters', 'gates', 'scopes', 'sorts', 'selects', 'aggregates', 'includes', 'instructions', 'limit'], array_keys($components->schemas['ShowActionSearch']->type->properties));
        $this->assertSame(['text', 'trashed', 'filters', 'scopes', 'instructions'], array_keys($components->schemas['ShowMetricSearch']->type->properties));
        $this->assertArrayNotHasKey('ShowActionSearch', $this->document()['components']['schemas']);
    }
}
