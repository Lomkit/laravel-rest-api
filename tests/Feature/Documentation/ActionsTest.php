<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Http\Request;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Scramble\Actions\ActionExample;
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
            'a search and every field' => ['/shows/actions/reschedule-shows', '{"search":{"filters":[{"field":"title","value":"Opening night"}]},"fields":[{"name":"at","value":"2026-01-01T00:00:00Z"},{"name":"notify","value":true}]}'],
            'a key and a declared example' => ['/shows/actions/cancel-shows', '{"resources":[1],"fields":[{"name":"reason","value":"Rain"}]}'],
            'an empty body' => ['/shows/actions/import-shows', '{}'],
            'a search' => ['/shows/actions/archive-shows', '{"search":{"filters":[{"field":"title","value":"Opening night"}]}}'],
        ];
    }

    #[Test]
    public function it_gives_each_field_its_declared_example_then_its_default_then_a_value_of_its_type(): void
    {
        $resource = (new RestContext(RestRequest::createFrom(Request::create('/api/shows/actions/cancel-shows', 'POST')), $this->app))->resource(ShowResource::class);
        $action = new class extends Action
        {
            public ActionTarget $target = ActionTarget::None;

            #[\Override]
            public function fields(): array
            {
                return [
                    Text::make('reason')
                        ->default('Storm')
                        ->examples('Rain'),
                    Integer::make('delay')->default(0),
                    Text::make('note'),
                ];
            }
        };

        $this->assertSame('{"fields":[{"name":"reason","value":"Rain"},{"name":"delay","value":0},{"name":"note","value":"a"}]}', json_encode(ActionExample::of($resource, $action)));
    }

    #[Test]
    #[DataProvider('results')]
    public function it_documents_the_result_of_an_action_by_its_type(string $component, string $schema): void
    {
        $this->assertSame($schema, json_encode($this->document()['components']['schemas'][$component]));
    }

    /** @return array<string, array{string, string}> */
    public static function results(): array
    {
        $data = '"data":{"type":"object","description":"Present when the action adds data to its result with `withData()`"}';

        return [
            'the result' => ['RestActionResult', '{"discriminator":{"propertyName":"type","mapping":{"message":"#\/components\/schemas\/RestActionResultMessage","danger":"#\/components\/schemas\/RestActionResultDanger","download":"#\/components\/schemas\/RestActionResultDownload","redirect":"#\/components\/schemas\/RestActionResultRedirect","created":"#\/components\/schemas\/RestActionResultCreated","data":"#\/components\/schemas\/RestActionResultData"}},"oneOf":[{"$ref":"#\/components\/schemas\/RestActionResultMessage"},{"$ref":"#\/components\/schemas\/RestActionResultDanger"},{"$ref":"#\/components\/schemas\/RestActionResultDownload"},{"$ref":"#\/components\/schemas\/RestActionResultRedirect"},{"$ref":"#\/components\/schemas\/RestActionResultCreated"},{"$ref":"#\/components\/schemas\/RestActionResultData"}],"title":"RestActionResult"}'],
            'a message' => ['RestActionResultMessage', '{"type":"object","description":"A message for the user","properties":{"type":{"type":"string","const":"message"},"message":{"type":"string"},'.$data.'},"required":["type","message"],"title":"RestActionResultMessage"}'],
            'a warning' => ['RestActionResultDanger', '{"type":"object","description":"A warning for the user","properties":{"type":{"type":"string","const":"danger"},"message":{"type":"string"},'.$data.'},"required":["type","message"],"title":"RestActionResultDanger"}'],
            'a download' => ['RestActionResultDownload', '{"type":"object","description":"A file for the client to download","properties":{"type":{"type":"string","const":"download"},"url":{"type":"string"},"name":{"type":"string","description":"The name of the file"},'.$data.'},"required":["type","url","name"],"title":"RestActionResultDownload"}'],
            'a redirect' => ['RestActionResultRedirect', '{"type":"object","description":"A URL for the client to open","properties":{"type":{"type":"string","const":"redirect"},"url":{"type":"string"},'.$data.'},"required":["type","url"],"title":"RestActionResultRedirect"}'],
            'the created rows' => ['RestActionResultCreated', '{"type":"object","description":"The keys of the rows that the action created","properties":{"type":{"type":"string","const":"created"},"keys":{"type":"array","description":"In the order that the action gives the rows","items":{"oneOf":[{"type":"integer"},{"type":"string"}]}},'.$data.'},"required":["type","keys"],"title":"RestActionResultCreated"}'],
            'data' => ['RestActionResultData', '{"type":"object","description":"Data for the client","properties":{"type":{"type":"string","const":"data"},"data":{"type":"object","description":"The data of the action"}},"required":["type","data"],"title":"RestActionResultData"}'],
        ];
    }

    #[Test]
    public function it_documents_the_batch_of_a_batchable_action_only(): void
    {
        $document = $this->document();
        $schemas = $document['components']['schemas'];
        $impacted = '"impacted":{"type":"integer","description":"The number of rows that the action ran on","minimum":0}';
        $result = '"result":{"anyOf":[{"$ref":"#\/components\/schemas\/RestActionResult"},{"type":"null"}]}';

        $this->assertSame('{"$ref":"#\/components\/schemas\/RestActionResponse"}', json_encode($document['paths']['/shows/actions/reschedule-shows']['post']['responses'][200]['content']['application/json']['schema']));
        $this->assertSame('{"$ref":"#\/components\/schemas\/RestBatchableActionResponse"}', json_encode($document['paths']['/shows/actions/archive-shows']['post']['responses'][200]['content']['application/json']['schema']));
        $this->assertSame('{"type":"object","properties":{"data":{"type":"object","properties":{'.$impacted.','.$result.'},"required":["impacted","result"]}},"required":["data"],"title":"RestActionResponse"}', json_encode($schemas['RestActionResponse']));
        $this->assertSame('{"type":"object","properties":{"data":{"type":"object","properties":{'.$impacted.',"batch":{"type":"string","description":"The id of the batch of the queued jobs of the action"},'.$result.'},"required":["impacted","batch","result"]}},"required":["data"],"title":"RestBatchableActionResponse"}', json_encode($schemas['RestBatchableActionResponse']));
    }

    #[Test]
    public function it_documents_the_progress_of_a_batch_of_a_batchable_action(): void
    {
        $document = $this->document();
        $operation = $document['paths']['/shows/actions/{action}/batches/{batch}']['get'];

        $this->assertSame('[{"name":"action","in":"path","required":true,"schema":{"type":"string","enum":["archive-shows"]}},{"name":"batch","in":"path","required":true,"schema":{"type":"string"}}]', json_encode($operation['parameters']));
        $this->assertSame('{"$ref":"#\/components\/schemas\/RestBatchProgress"}', json_encode($operation['responses'][200]['content']['application/json']['schema']));
        $this->assertSame(
            '{"type":"object","properties":{"data":{"type":"object","properties":{"id":{"type":"string"},"total_jobs":{"type":"integer"},"pending_jobs":{"type":"integer"},"failed_jobs":{"type":"integer"},"progress":{"type":"integer","description":"The share of the jobs that ran, in percent","minimum":0,"maximum":100},"finished":{"type":"boolean"},"cancelled":{"type":"boolean"}},"required":["id","total_jobs","pending_jobs","failed_jobs","progress","finished","cancelled"]}},"required":["data"],"title":"RestBatchProgress"}',
            json_encode($document['components']['schemas']['RestBatchProgress']),
        );
    }
}
