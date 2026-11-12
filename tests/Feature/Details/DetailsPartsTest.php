<?php

namespace Lomkit\Rest\Tests\Feature\Details;

use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Tests\Fixtures\Gallery\ArtistResource;
use Lomkit\Rest\Tests\Fixtures\Gallery\Collector;
use Lomkit\Rest\Tests\Fixtures\Gallery\CollectorResource;
use Lomkit\Rest\Tests\Fixtures\Gallery\ExhibitionResource;
use Lomkit\Rest\Tests\Fixtures\Gallery\Gallery;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

class DetailsPartsTest extends PackageTestCase
{
    private const string BASE_FIELDS = '[{"name":"id","label":null,"type":"id","nullable":false},{"name":"title","label":"Title","type":"text","nullable":false},{"name":"medium","label":null,"type":"enum","nullable":false,"values":["oil","ink","watercolor"]},{"name":"dimensions","label":null,"type":"json","nullable":true},{"name":"price","label":null,"type":"number","nullable":true},{"name":"notes","label":null,"type":"text","nullable":true}]';

    private const string TITLE = '{"name":"title","label":"Title","type":"text","nullable":false';

    private const string TEXT_OPERATORS = '["=","!=","like","not like","ilike","not ilike","in","not in"]';

    private const string ACTION = '{"name":"Retire Artworks","uriKey":"retire-artworks","target":"search"';

    private const string ACTION_HINTS = '"destructive":false,"confirm":null,"confirmation":false';

    private const string INPUT_FIELD = '{"name":"notify","label":null,"type":"boolean","nullable":false,"default":false,"rules":["boolean"],"meta":{}}';

    private const string MEDIUM_FIELD = '{"name":"medium","label":null,"type":"enum","nullable":false,"default":null,"rules":["enum","required"],"meta":{},"values":["oil","ink","watercolor"]}';

    private const string SCOPE_FIELD = '{"name":"max","label":null,"type":"number","nullable":false,"default":null,"rules":["numeric"],"meta":{}}';

    private const string CONSTRAINTS = '{"required_on_creation":false,"prohibited_on_creation":false,"required_on_update":false,"prohibited_on_update":false}';

    private const string ALLOWED = '{"allowed":true,"message":null}';

    private const string ACCEPTED = 'fields.capabilities, fields.filter, fields.rules, fields.meta, relations, relations.constraints, relations.operations, relations.pivot_fields, actions, actions.fields, actions.authorized, actions.meta, metrics, metrics.authorized, metrics.meta, instructions, instructions.fields, instructions.meta, scopes, scopes.fields, scopes.meta, rules, text, gates, confirmations, authorizations';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap([], false);
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Gallery];
    }

    #[Test]
    public function it_answers_the_fields_the_limits_and_the_meta_without_with(): void
    {
        $this->assertSame('{"fields":'.self::BASE_FIELDS.',"limits":[10,25,50],"meta":{}}', json_encode($this->data('/api/artworks')));
    }

    #[Test]
    public function it_answers_the_same_base_for_an_empty_with(): void
    {
        $this->assertSame('{"fields":'.self::BASE_FIELDS.',"limits":[10,25,50],"meta":{}}', json_encode($this->data('/api/artworks?with=')));
    }

    /** @param list<string> $keys */
    #[Test]
    #[DataProvider('fieldParts')]
    public function it_adds_a_part_to_each_field(string $with, string $title, array $keys): void
    {
        $data = $this->data("/api/artworks?with={$with}");

        $this->assertSame(['fields', 'limits', 'meta'], array_keys((array) $data));
        $this->assertSame(self::TITLE.$title.'}', json_encode($data->fields[1]));
        $this->assertSame($keys, array_keys((array) $data->fields[2]));
    }

    /** @return array<string, array{string, string, list<string>}> */
    public static function fieldParts(): array
    {
        $base = ['name', 'label', 'type', 'nullable'];

        return [
            'the capabilities' => ['fields.capabilities', ',"select":true,"sort":true,"create":true,"update":true', [...$base, 'select', 'sort', 'create', 'update', 'values']],
            'the filter' => ['fields.filter', ',"filter":{"operators":'.self::TEXT_OPERATORS.'}', [...$base, 'filter', 'values']],
            'the rules' => ['fields.rules', ',"rules":{"all":["string","max:120"],"create":["required"],"update":["filled"]}', [...$base, 'rules', 'values']],
            'the meta' => ['fields.meta', ',"meta":{}', [...$base, 'meta', 'values']],
            'every part' => [
                'fields.meta,fields.rules,fields.filter,fields.capabilities',
                ',"select":true,"sort":true,"filter":{"operators":'.self::TEXT_OPERATORS.'},"create":true,"update":true,"rules":{"all":["string","max:120"],"create":["required"],"update":["filled"]},"meta":{}',
                [...$base, 'select', 'sort', 'filter', 'create', 'update', 'rules', 'meta', 'values'],
            ],
        ];
    }

    /** @param list<string> $keys */
    #[Test]
    #[DataProvider('sections')]
    public function it_adds_a_section_or_a_part_of_it(string $with, string $section, string $expected, array $keys): void
    {
        $data = $this->data("/api/artworks?with={$with}");

        $this->assertSame($keys, array_keys((array) $data));
        $this->assertSame($expected, json_encode($data->{$section}));
    }

    /** @return array<string, array{string, string, string, list<string>}> */
    public static function sections(): array
    {
        return [
            'the relations' => ['relations', 'relations', self::relations('', '', ''), ['fields', 'limits', 'relations', 'meta']],
            'the constraints of the relations' => ['relations.constraints', 'relations', self::relations(',"constraints":'.self::CONSTRAINTS, '', ''), ['fields', 'limits', 'relations', 'meta']],
            'the operations of the relations' => ['relations.operations', 'relations', self::relations('', ',"operations":', ''), ['fields', 'limits', 'relations', 'meta']],
            'the pivot fields of the relations' => ['relations.pivot_fields', 'relations', self::relations('', '', ',"pivot_fields":'), ['fields', 'limits', 'relations', 'meta']],
            'the actions' => ['actions', 'actions', '['.self::ACTION.','.self::ACTION_HINTS.'}]', ['actions', 'fields', 'limits', 'meta']],
            'the fields of the actions' => ['actions.fields', 'actions', '['.self::ACTION.',"fields":['.self::INPUT_FIELD.'],'.self::ACTION_HINTS.'}]', ['actions', 'fields', 'limits', 'meta']],
            'the meta of the actions' => ['actions.meta', 'actions', '['.self::ACTION.',"meta":{},'.self::ACTION_HINTS.'}]', ['actions', 'fields', 'limits', 'meta']],
            'the flags of the actions' => ['actions.authorized', 'actions', '['.self::ACTION.','.self::ACTION_HINTS.',"authorized":true}]', ['actions', 'fields', 'limits', 'meta']],
            'the metrics' => ['metrics', 'metrics', '[]', ['metrics', 'fields', 'limits', 'meta']],
            'the instructions' => ['instructions', 'instructions', '[{"name":"By Medium","uriKey":"by-medium","text":true}]', ['instructions', 'fields', 'limits', 'meta']],
            'the fields of the instructions' => ['instructions.fields', 'instructions', '[{"name":"By Medium","uriKey":"by-medium","fields":['.self::MEDIUM_FIELD.'],"text":true}]', ['instructions', 'fields', 'limits', 'meta']],
            'the meta of the instructions' => ['instructions.meta', 'instructions', '[{"name":"By Medium","uriKey":"by-medium","meta":{},"text":true}]', ['instructions', 'fields', 'limits', 'meta']],
            'the scopes' => ['scopes', 'scopes', '[{"name":"pricedUnder"}]', ['fields', 'limits', 'scopes', 'meta']],
            'the fields of the scopes' => ['scopes.fields', 'scopes', '[{"name":"pricedUnder","fields":['.self::SCOPE_FIELD.']}]', ['fields', 'limits', 'scopes', 'meta']],
            'the meta of the scopes' => ['scopes.meta', 'scopes', '[{"name":"pricedUnder","meta":{}}]', ['fields', 'limits', 'scopes', 'meta']],
            'the rules' => ['rules', 'rules', '{"all":{},"create":{"price":["required","min:0"]},"update":{}}', ['fields', 'limits', 'rules', 'meta']],
            'the text search' => ['text', 'text', '{"fields":["title"],"modes":["words","phrase"],"relevance":false,"trashed":false,"max_length":255,"engine":null}', ['fields', 'limits', 'text', 'meta']],
            'the gates' => ['gates', 'gates', '["view","create","update","delete"]', ['fields', 'limits', 'gates', 'meta']],
            'the authorizations' => ['authorizations', 'authorizations', '{"viewAny":'.self::ALLOWED.',"create":'.self::ALLOWED.'}', ['fields', 'limits', 'authorizations', 'meta']],
        ];
    }

    #[Test]
    public function it_adds_the_section_of_each_part_once(): void
    {
        $data = $this->data('/api/artworks?with=scopes.meta,actions.meta,scopes.fields,actions.meta');

        $this->assertSame(['actions', 'fields', 'limits', 'scopes', 'meta'], array_keys((array) $data));
        $this->assertSame('[{"name":"pricedUnder","fields":['.self::SCOPE_FIELD.'],"meta":{}}]', json_encode($data->scopes));
        $this->assertSame('['.self::ACTION.',"meta":{},'.self::ACTION_HINTS.'}]', json_encode($data->actions));
    }

    #[Test]
    public function it_answers_a_repeated_value_once(): void
    {
        $this->assertSame(json_encode($this->data('/api/artworks?with=gates')), json_encode($this->data('/api/artworks?with=gates,gates')));
    }

    #[Test]
    #[DataProvider('unknownValues')]
    public function it_refuses_a_value_that_is_not_a_section_or_a_part(string $with): void
    {
        $this->getJson("/api/artworks?with={$with}")
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'The with field accepts these values, separated by commas: '.self::ACCEPTED.'.',
                'errors' => ['with' => ['The with field accepts these values, separated by commas: '.self::ACCEPTED.'.']],
            ]);
    }

    /** @return array<string, array{string}> */
    public static function unknownValues(): array
    {
        return [
            'an unknown section' => ['everything'],
            'a wildcard' => ['*'],
            'the fields, which are always there' => ['fields'],
            'the limits, which are always there' => ['gates,limits'],
            'an unknown part' => ['actions.authorizations'],
            'a third level' => ['actions.fields.rules'],
            'a space after a comma' => ['gates,%20text'],
            'an empty value between two commas' => ['gates,,text'],
            'another case' => ['Gates'],
        ];
    }

    #[Test]
    public function it_refuses_a_list_of_values(): void
    {
        $this->getJson('/api/artworks?with[]=gates')
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'The with field must be a string.', 'errors' => ['with' => ['The with field must be a string.']]]);
    }

    private static function relations(string $constraints, string $operations, string $pivotFields): string
    {
        $pivot = '[{"name":"position","label":null,"type":"integer","nullable":true,"select":true,"sort":false,"filter":{"operators":["=","!=",">",">=","<","<=","between","not between","in","not in","is null","is not null"]},"create":true,"update":true,"rules":{"all":["nullable","integer"],"create":[],"update":[]},"meta":{}},'
            .'{"name":"wall","label":null,"type":"text","nullable":false,"select":true,"sort":false,"filter":{"operators":'.self::TEXT_OPERATORS.'},"create":true,"update":false,"rules":{"all":["string"],"create":[],"update":[]},"meta":{}}]';
        $one = $operations === '' ? '' : $operations.'["create","update","attach","detach"],"sync":null';
        $many = $operations === '' ? '' : $operations.'["create","update","attach","detach","sync","toggle"],"sync":"replace"';

        return '['
            .'{"resource":'.json_encode(ArtistResource::class).',"relation":"artist"'.$constraints.$one.',"name":"BelongsTo"},'
            .'{"resource":'.json_encode(ExhibitionResource::class).',"relation":"exhibitions"'.$constraints.$many.',"name":"BelongsToMany"'.($pivotFields === '' ? '' : $pivotFields.$pivot).'},'
            .'{"resource":'.json_encode(CollectorResource::class).',"relation":"owner"'.$constraints.$one.',"name":"MorphTo","types":{'.json_encode(Collector::class).':'.json_encode(CollectorResource::class).'},"morph":{"type_column":"owner_type","key_column":"owner_id"}}'
            .']';
    }

    private function data(string $uri): stdClass
    {
        $content = $this->getJson($uri)
            ->assertOk()
            ->getContent();

        $data = json_decode((string) $content, flags: JSON_THROW_ON_ERROR)->data;

        $this->assertInstanceOf(stdClass::class, $data);

        return $data;
    }
}
