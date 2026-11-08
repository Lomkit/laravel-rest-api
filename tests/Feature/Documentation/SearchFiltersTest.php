<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SearchFiltersTest extends DocumentationTestCase
{
    private const string JOINT = '"type":{"type":"string","enum":["and","or"]}';

    #[Test]
    public function it_documents_the_filters_of_a_search_as_conditions_on_fields_on_related_rows_or_groups(): void
    {
        $document = $this->document();
        $schemas = $document['components']['schemas'];

        $this->assertSame('{"type":"array","items":{"$ref":"#\/components\/schemas\/ShowFilter"},"maxItems":100}', json_encode($this->search($document, '/shows/search')['filters']));
        $this->assertSame(
            '{"description":"A condition on a field, a condition on related rows, or a group of filters. `type` joins it to the previous filter: `and`, the default, or `or`.","oneOf":[{"$ref":"#\/components\/schemas\/ShowFieldFilter"},{"$ref":"#\/components\/schemas\/ShowRelationFilter"},{"$ref":"#\/components\/schemas\/ShowFilterGroup"}],"title":"ShowFilter"}',
            json_encode($schemas['ShowFilter']),
        );
        $this->assertSame(
            '{"type":"object","description":"Filters that count as one condition. Groups, and the `nested` filters of related rows, nest at most 1 deep.","properties":{"nested":{"type":"array","items":{"$ref":"#\/components\/schemas\/ShowFilter"},"maxItems":100},'.self::JOINT.'},"required":["nested"],"not":{"anyOf":[{"required":["field"]},{"required":["operator"]},{"required":["value"]},{"required":["relation"]}]},"title":"ShowFilterGroup"}',
            json_encode($schemas['ShowFilterGroup']),
        );
    }

    #[Test]
    #[DataProvider('fields')]
    public function it_documents_the_operators_and_the_value_of_each_filterable_field(string $component, string $schema): void
    {
        $this->assertSame($schema, json_encode($this->document()['components']['schemas'][$component]));
    }

    /** @return array<string, array{string, string}> */
    public static function fields(): array
    {
        return [
            'a boolean' => ['ShowFilterFeatured', '{"type":"object","properties":{"field":{"type":"string","const":"featured"},"operator":{"type":"string","enum":["=","!="]},"value":{"type":"boolean"},'.self::JOINT.'},"required":["field","value"],"not":{"anyOf":[{"required":["nested"]},{"required":["relation"]}]},"title":"ShowFilterFeatured"}'],
            'an integer' => ['ShowFilterSeats', '{"oneOf":['.self::branch('seats', '"=","!=",">",">=","<","<="', '{"type":"integer"}', false).','.self::branch('seats', '"between","not between"', '{"type":"array","items":{"type":"integer"},"minItems":2,"maxItems":2}').','.self::branch('seats', '"in","not in"', '{"type":"array","items":{"type":"integer"},"minItems":1,"maxItems":100}').'],"title":"ShowFilterSeats"}'],
            'a nullable text' => ['ShowFilterSummary', '{"oneOf":['.self::branch('summary', '"=","!="', '{"type":"string"}', false).','.self::branch('summary', '"like","not like","ilike","not ilike"', '{"type":"string","maxLength":255}').','.self::branch('summary', '"in","not in"', '{"type":"array","items":{"type":"string"},"minItems":1,"maxItems":100}').','.self::nullity('summary').'],"title":"ShowFilterSummary"}'],
            'a text with examples' => ['ShowFilterTitle', '{"oneOf":['.self::branch('title', '"=","!="', '{"type":"string","examples":["Opening night","Closing night"]}', false).','.self::branch('title', '"like","not like","ilike","not ilike"', '{"type":"string","maxLength":255}').','.self::branch('title', '"in","not in"', '{"type":"array","items":{"type":"string","examples":["Opening night","Closing night"]},"minItems":1,"maxItems":100}').'],"title":"ShowFilterTitle"}'],
            'a date' => ['ShowFilterStartsOn', '{"oneOf":['.self::branch('starts_on', '"=","!=",">",">=","<","<="', '{"type":"string","format":"date"}', false).','.self::branch('starts_on', '"between","not between"', '{"type":"array","items":{"type":"string","format":"date"},"minItems":2,"maxItems":2}').'],"title":"ShowFilterStartsOn"}'],
            'an enum backed by integers' => ['ShowFilterLevel', '{"oneOf":['.self::branch('level', '"=","!="', '{"type":"integer","enum":[1,2,3]}', false).','.self::branch('level', '"in","not in"', '{"type":"array","items":{"type":"integer","enum":[1,2,3]},"minItems":1,"maxItems":100}').'],"title":"ShowFilterLevel"}'],
            'a string key with an example' => ['StageFilterCode', '{"oneOf":['.self::branch('code', '"=","!="', '{"type":"string","examples":["main"]}', false).','.self::branch('code', '"in","not in"', '{"type":"array","items":{"type":"string","examples":["main"]},"minItems":1,"maxItems":100}').'],"title":"StageFilterCode"}'],
            'a nullable pivot field' => ['ShowFilterLabels_Pivot_Position', '{"oneOf":['.self::branch('labels.pivot.position', '"=","!=",">",">=","<","<="', '{"type":"integer"}', false).','.self::branch('labels.pivot.position', '"between","not between"', '{"type":"array","items":{"type":"integer"},"minItems":2,"maxItems":2}').','.self::branch('labels.pivot.position', '"in","not in"', '{"type":"array","items":{"type":"integer"},"minItems":1,"maxItems":100}').','.self::nullity('labels.pivot.position').'],"title":"ShowFilterLabels_Pivot_Position"}'],
        ];
    }

    #[Test]
    public function it_lists_the_filterable_fields_and_the_fields_of_each_relation_one_level_deep(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertSame(
            ['id', 'title', 'summary', 'price', 'seats', 'featured', 'starts_on', 'starts_at', 'status', 'level', 'stage.code', 'stage.name', 'artists.id', 'artists.name', 'artists.pivot.role', 'comments.id', 'comments.rating', 'comments.body', 'labels.id', 'labels.name', 'labels.pivot.position'],
            array_keys((array) $schemas['ShowFieldFilter']['discriminator']['mapping']),
        );
        $this->assertSame('#/components/schemas/ShowFilterArtists_Pivot_Role', ((array) $schemas['ShowFieldFilter']['discriminator']['mapping'])['artists.pivot.role']);
        $this->assertSame(['code', 'name', 'shows.id', 'shows.title', 'shows.summary', 'shows.price', 'shows.seats', 'shows.featured', 'shows.starts_on', 'shows.starts_at', 'shows.status', 'shows.level', 'comments.id', 'comments.rating', 'comments.body'], array_keys((array) $schemas['StageFieldFilter']['discriminator']['mapping']));
        $this->assertSame(['id', 'body'], array_keys((array) $schemas['NoteFieldFilter']['discriminator']['mapping']));
        $this->assertSame(
            'A condition on a field. Without `operator`, the condition is `=`. The fields of each related resource are listed one level deep, such as `stage.name`; deeper dotted paths are accepted too and are not listed.',
            $schemas['ShowFieldFilter']['description'],
        );
    }

    #[Test]
    #[DataProvider('relatedFields')]
    public function it_documents_a_field_of_a_related_resource_with_the_operators_and_the_value_of_its_own_filter(string $component, string $own, string $path, string $field): void
    {
        $schemas = $this->document()['components']['schemas'];
        $expected = str_replace(['"const":"'.$field.'"', '"title":"'.$own.'"'], ['"const":"'.$path.'"', '"title":"'.$component.'"'], (string) json_encode($schemas[$own]));

        $this->assertSame('#/components/schemas/'.$component, ((array) $schemas['ShowFieldFilter']['discriminator']['mapping'])[$path]);
        $this->assertSame($expected, json_encode($schemas[$component]));
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function relatedFields(): array
    {
        return [
            'a field of a relation to one' => ['ShowFilterStage_Name', 'StageFilterName', 'stage.name', 'name'],
            'a field of a relation to many' => ['ShowFilterComments_Rating', 'CommentFilterRating', 'comments.rating', 'rating'],
        ];
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_documents_a_condition_on_the_related_rows_of_each_relation(string $component, string $schema): void
    {
        $this->assertSame($schema, json_encode($this->document()['components']['schemas'][$component]));
    }

    /** @return array<string, array{string, string}> */
    public static function relations(): array
    {
        $head = fn (string $relation): string => '{"type":"object","properties":{"relation":{"type":"string","const":"'.$relation.'"},"exists":{"type":"boolean"},'.self::JOINT;

        return [
            'a relation to one' => ['ShowRelationFilterStage', $head('stage').',"nested":{"type":"array","items":{"$ref":"#\/components\/schemas\/StageFilter"},"maxItems":100}},"required":["relation"],"title":"ShowRelationFilterStage"}'],
            'a relation through another' => ['StageRelationFilterComments', $head('comments').',"nested":{"type":"array","items":{"$ref":"#\/components\/schemas\/CommentFilter"},"maxItems":100}},"required":["relation"],"title":"StageRelationFilterComments"}'],
            'a morph to relation of two types' => ['NoteRelationFilterNotable', $head('notable').',"types":{"type":"object","description":"The filters of each type of related row, keyed by morph type","properties":{"Lomkit\\\\Rest\\\\Tests\\\\Fixtures\\\\Festival\\\\Show":{"type":"array","items":{"$ref":"#\/components\/schemas\/ShowFilter"},"maxItems":100},"Lomkit\\\\Rest\\\\Tests\\\\Fixtures\\\\Festival\\\\Stage":{"type":"array","items":{"$ref":"#\/components\/schemas\/StageFilter"},"maxItems":100}}}},"required":["relation"],"not":{"required":["nested"]},"title":"NoteRelationFilterNotable"}'],
        ];
    }

    #[Test]
    #[WithConfig('rest.limits.sizes.filters', 7)]
    #[WithConfig('rest.limits.sizes.values', 5)]
    #[WithConfig('rest.limits.lengths.patterns', 40)]
    #[WithConfig('rest.limits.depths.filters', 2)]
    public function it_takes_the_list_sizes_the_depth_and_the_pattern_length_from_the_configuration(): void
    {
        $document = $this->document();
        $schemas = $document['components']['schemas'];

        $this->assertSame(7, $this->search($document, '/shows/search')['filters']['maxItems']);
        $this->assertSame(7, $schemas['ShowRelationFilterStage']['properties']['nested']['maxItems']);
        $this->assertSame(40, $schemas['ShowFilterTitle']['oneOf'][1]['properties']['value']['maxLength']);
        $this->assertSame(5, $schemas['ShowFilterTitle']['oneOf'][2]['properties']['value']['maxItems']);
        $this->assertSame('Filters that count as one condition. Groups, and the `nested` filters of related rows, nest at most 2 deep.', $schemas['ShowFilterGroup']['description']);
    }

    #[Test]
    #[DataProvider('examples')]
    public function it_gives_each_search_a_request_example_that_filters_on_a_field(string $path, string $example): void
    {
        $this->assertSame($example, json_encode($this->operation($path, 'post')['requestBody']['content']['application/json']['examples']));
    }

    /** @return array<string, array{string, string}> */
    public static function examples(): array
    {
        return [
            'the first filterable field' => ['/notes/search', '{"default":{"value":{"search":{"filters":[{"field":"id","value":1}]}}}}'],
            'the first filterable field with examples' => ['/shows/search', '{"default":{"value":{"search":{"filters":[{"field":"title","value":"Opening night"}]}}}}'],
            'a string key with an example' => ['/stages/search', '{"default":{"value":{"search":{"filters":[{"field":"code","value":"main"}]}}}}'],
        ];
    }

    private static function branch(string $field, string $operators, string $value, bool $operator = true): string
    {
        return '{"type":"object","properties":{"field":{"type":"string","const":"'.$field.'"},"operator":{"type":"string","enum":['.$operators.']},"value":'.$value.','.self::JOINT.'},"required":["field",'.($operator ? '"operator",' : '').'"value"],"not":{"anyOf":[{"required":["nested"]},{"required":["relation"]}]}}';
    }

    private static function nullity(string $field): string
    {
        return '{"type":"object","properties":{"field":{"type":"string","const":"'.$field.'"},"operator":{"type":"string","enum":["is null","is not null"]},'.self::JOINT.'},"required":["field","operator"],"not":{"anyOf":[{"required":["value"]},{"required":["nested"]},{"required":["relation"]}]}}';
    }
}
