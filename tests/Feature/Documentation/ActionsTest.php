<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Http\Request;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Scramble\Components\KeySchema;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ActionsTest extends DocumentationTestCase
{
    private const string FIELDS = '"type":"array","description":"The values of the fields of the action: one `{\"name\", \"value\"}` entry for each field that the request gives"';

    #[Test]
    #[DataProvider('bodies')]
    public function it_documents_the_body_of_each_action_by_its_target(string $path, string $body): void
    {
        $this->assertSame($body, json_encode($this->operation($path, 'post')['requestBody']['content']['application/json']['schema']));
    }

    /** @return array<string, array{string, string}> */
    public static function bodies(): array
    {
        return [
            'a search without a cap and two fields' => ['/shows/actions/reschedule-shows', '{"type":"object","description":"The action runs on the rows that `search` matches, every row without `search`","properties":{"search":{"$ref":"#\/components\/schemas\/ShowActionSearch"},"resources":false,"fields":{'.self::FIELDS.',"items":{"oneOf":[{"type":"object","properties":{"name":{"type":"string","const":"at"},"value":{"type":["string","null"],"format":"date-time","description":"Rules: nullable, date"}},"required":["name"],"additionalProperties":false},{"type":"object","properties":{"name":{"type":"string","const":"notify"},"value":{"type":"boolean","description":"Rules: boolean"}},"required":["name"],"additionalProperties":false}]},"maxItems":100}}}'],
            'keys and one field' => ['/shows/actions/cancel-shows', '{"type":"object","description":"The action runs on the rows of the keys in `resources`","properties":{"search":false,"resources":{"type":"array","items":{"type":"integer"},"minItems":1,"maxItems":2,"uniqueItems":true},"fields":{'.self::FIELDS.',"items":{"type":"object","properties":{"name":{"type":"string","const":"reason"},"value":{"type":["string","null"],"description":"Rules: nullable, string","examples":["Rain"]}},"required":["name"],"additionalProperties":false},"maxItems":100}},"required":["resources"]}'],
            'no rows and no field' => ['/shows/actions/import-shows', '{"type":"object","description":"The action runs without rows","properties":{"search":false,"resources":false,"fields":false}}'],
            'a search with the default cap' => ['/shows/actions/archive-shows', '{"type":"object","description":"The action runs on the rows that `search` matches, every row without `search`, at most 1000: a search that matches more answers 422","properties":{"search":{"$ref":"#\/components\/schemas\/ShowActionSearch"},"resources":false,"fields":false}}'],
        ];
    }

    #[Test]
    public function it_documents_the_search_of_an_action_with_the_keys_that_it_reads(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertSame(['text', 'trashed', 'filters', 'gates', 'scopes', 'sorts', 'selects', 'aggregates', 'includes', 'instructions', 'limit'], array_keys($schemas['ShowActionSearch']['properties']));
        $this->assertArrayNotHasKey('NoteActionSearch', $schemas);
        $this->assertArrayNotHasKey('StageActionSearch', $schemas);
    }

    #[Test]
    #[WithConfig('rest.limits.sizes.fields', 3)]
    public function it_takes_the_size_of_the_fields_of_an_action_from_the_configuration(): void
    {
        $body = $this->operation('/shows/actions/reschedule-shows', 'post')['requestBody']['content']['application/json']['schema'];

        $this->assertSame(3, $body['properties']['fields']['maxItems']);
    }

    #[Test]
    #[DataProvider('caps')]
    public function it_caps_the_keys_of_an_action_with_its_maximum(?int $max, string $keys): void
    {
        $resource = (new RestContext(RestRequest::createFrom(Request::create('/api/shows/actions/cancel-shows', 'POST')), $this->app))->resource(ShowResource::class);

        $this->assertSame($keys, json_encode(KeySchema::capped($resource, $max)->serializeAs31()));
    }

    /** @return array<string, array{?int, string}> */
    public static function caps(): array
    {
        return [
            'a maximum' => [5, '{"type":"array","items":{"type":"integer"},"minItems":1,"maxItems":5,"uniqueItems":true}'],
            'no maximum' => [null, '{"type":"array","items":{"type":"integer"},"minItems":1,"uniqueItems":true}'],
        ];
    }

    #[Test]
    #[DataProvider('examples')]
    public function it_gives_each_action_a_request_example(string $path, string $example): void
    {
        $this->assertSame($example, json_encode($this->operation($path, 'post')['requestBody']['content']['application/json']['examples']['default']['value']));
    }

    /** @return array<string, array{string, string}> */
    public static function examples(): array
    {
        return [
            'a search and the fields without a default' => ['/shows/actions/reschedule-shows', '{"search":{"filters":[{"field":"title","value":"Opening night"}]},"fields":[{"name":"at","value":"2026-01-01T00:00:00Z"}]}'],
            'a key and a declared example' => ['/shows/actions/cancel-shows', '{"resources":[1],"fields":[{"name":"reason","value":"Rain"}]}'],
            'an empty body' => ['/shows/actions/import-shows', '{}'],
            'a search' => ['/shows/actions/archive-shows', '{"search":{"filters":[{"field":"title","value":"Opening night"}]}}'],
        ];
    }
}
