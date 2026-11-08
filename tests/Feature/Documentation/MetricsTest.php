<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MetricsTest extends DocumentationTestCase
{
    private const string BODY = '"type":"object","description":"The metric runs on the rows that `search` matches, every row without `search`","properties":{"search":{"$ref":"#\/components\/schemas\/ShowMetricSearch"},';

    private const string TIMEZONE = '"timezone":{"type":"string","description":"The time zone of the periods of the metric, such as `Europe\/Paris`; the time zone of the application when the request sends none"}';

    private const string NUMBER = '{"type":["number","null"]}';

    private const string KEY = '{"type":["integer","string","boolean","null"]}';

    private const string LABEL = '{"type":["string","null"]}';

    private const string SERIES = '{"type":"array","items":{"type":"object","properties":{"label":{"type":"string"},"value":'.self::NUMBER.'},"required":["label","value"]}}';

    private const string TREND = '{"type":"object","properties":{"series":'.self::SERIES.',"splits":{"type":"array","description":"One series for each value of the split column with the largest totals, up to the limit of the metric, present when the trend is split","items":{"type":"object","properties":{"key":'.self::KEY.',"label":'.self::LABEL.',"series":'.self::SERIES.'},"required":["key","label","series"]},"maxItems":1},"other":{"type":["object","null"],"description":"The splits that the limit folds, present with `splits`","properties":{"label":'.self::LABEL.',"series":'.self::SERIES.'},"required":["label","series"]}},"required":["series"]}';

    private const array METRICS = [
        '/shows/metrics/show-count',
        '/shows/metrics/shows-per-month',
        '/shows/metrics/shows-by-status',
        '/shows/metrics/shows-by-level',
        '/shows/metrics/seats-by-level',
        '/shows/metrics/top-stages',
        '/shows/metrics/seats-goal',
        '/shows/metrics/occupancy',
    ];

    #[Test]
    #[DataProvider('bodies')]
    public function it_documents_the_body_of_each_metric(string $path, string $body): void
    {
        $this->assertSame($body, json_encode($this->operation($path, 'post')['requestBody']['content']['application/json']['schema']));
    }

    /** @return array<string, array{string, string}> */
    public static function bodies(): array
    {
        return [
            'a value with two ranges' => ['/shows/metrics/show-count', '{'.self::BODY.'"range":{"type":"integer","description":"The range of the metric: `30` when the request sends none","enum":[30,90]},'.self::TIMEZONE.'}}'],
            'a trend with two ranges' => ['/shows/metrics/shows-per-month', '{'.self::BODY.'"range":{"type":"integer","description":"The range of the metric: `3` when the request sends none","enum":[3,12]},'.self::TIMEZONE.'}}'],
            'a metric without ranges' => ['/shows/metrics/shows-by-status', '{'.self::BODY.'"range":false,'.self::TIMEZONE.'}}'],
        ];
    }

    #[Test]
    public function it_gives_each_metric_a_request_example_with_its_first_range_and_a_time_zone(): void
    {
        $document = $this->document();
        $examples = [];

        foreach (self::METRICS as $path) {
            $examples[$path] = json_encode($document['paths'][$path]['post']['requestBody']['content']['application/json']['examples']);
        }

        $this->assertSame([
            '/shows/metrics/show-count' => '{"default":{"value":{"range":30,"timezone":"UTC"}}}',
            '/shows/metrics/shows-per-month' => '{"default":{"value":{"range":3,"timezone":"UTC"}}}',
            '/shows/metrics/shows-by-status' => '{"default":{"value":{"timezone":"UTC"}}}',
            '/shows/metrics/shows-by-level' => '{"default":{"value":{"range":30,"timezone":"UTC"}}}',
            '/shows/metrics/seats-by-level' => '{"default":{"value":{"range":30,"timezone":"UTC"}}}',
            '/shows/metrics/top-stages' => '{"default":{"value":{"timezone":"UTC"}}}',
            '/shows/metrics/seats-goal' => '{"default":{"value":{"timezone":"UTC"}}}',
            '/shows/metrics/occupancy' => '{"default":{"value":{"timezone":"UTC"}}}',
        ], $examples);
    }

    #[Test]
    public function it_documents_the_search_of_a_metric_with_the_keys_that_it_reads(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertSame(['text', 'trashed', 'filters', 'scopes', 'instructions'], array_keys($schemas['ShowMetricSearch']['properties']));
        $this->assertArrayNotHasKey('NoteMetricSearch', $schemas);
        $this->assertArrayNotHasKey('StageMetricSearch', $schemas);
    }

    #[Test]
    #[DataProvider('responses')]
    public function it_documents_the_response_of_each_metric_with_its_type(string $path, string $data): void
    {
        $response = $this->operation($path, 'post')['responses'][200];

        $this->assertSame('The result of the metric', $response['description']);
        $this->assertSame('{"type":"object","properties":{"data":'.$data.'},"required":["data"]}', json_encode($response['content']['application/json']['schema']));
    }

    /** @return array<string, array{string, string}> */
    public static function responses(): array
    {
        return [
            'a value' => ['/shows/metrics/show-count', self::typed('value', '{"$ref":"#\/components\/schemas\/RestValueResult"}')],
            'a trend with its own limit' => ['/shows/metrics/shows-per-month', self::typed('trend', self::TREND)],
            'a partition' => ['/shows/metrics/shows-by-status', self::typed('partition', '{"$ref":"#\/components\/schemas\/RestPartitionResult"}')],
            'a partition that compares two periods, with its own limit' => ['/shows/metrics/shows-by-level', self::typed('partition', self::partition(2))],
            'a partition of percentiles that compares two periods' => ['/shows/metrics/seats-by-level', self::typed('partition', '{"$ref":"#\/components\/schemas\/RestPartitionResult"}')],
            'a table' => ['/shows/metrics/top-stages', self::typed('table', '{"$ref":"#\/components\/schemas\/RestTableResult"}')],
            'a progress' => ['/shows/metrics/seats-goal', self::typed('progress', '{"$ref":"#\/components\/schemas\/RestProgressResult"}')],
            'a type of the application with its schema' => ['/shows/metrics/occupancy', self::typed('occupancy', '{"type":"object","properties":{"seats":{"type":"integer"},"capacity":{"type":"integer"}},"required":["seats","capacity"]}')],
        ];
    }

    #[Test]
    #[DataProvider('results')]
    public function it_documents_the_result_of_each_built_in_type_once(string $component, string $schema): void
    {
        $this->assertSame($schema, json_encode($this->document()['components']['schemas'][$component]));
    }

    /** @return array<string, array{string, string}> */
    public static function results(): array
    {
        $number = '{"type":["number","null"]}';
        $key = '{"type":["integer","string","boolean","null"]}';
        $label = '{"type":["string","null"]}';

        return [
            'value' => ['RestValueResult', '{"type":"object","properties":{"value":'.$number.',"previous":{"type":["number","null"],"description":"The value of the period before the range"}},"required":["value","previous"],"title":"RestValueResult"}'],
            'partition' => ['RestPartitionResult', substr(self::partition(100), 0, -1).',"title":"RestPartitionResult"}'],
            'table' => ['RestTableResult', '{"type":"object","properties":{"columns":{"type":"array","items":{"type":"object","properties":{"name":{"type":"string"},"label":{"type":"string"},"aggregate":{"type":"string","enum":["count","sum","average","min","max","median","percentile"]},"percentile":{"type":"number","description":"The percentile of the column, from 0 to 1, present when `aggregate` is `percentile`","minimum":0,"maximum":1}},"required":["name","label","aggregate"]}},"rows":{"type":"array","items":{"type":"object","properties":{"key":'.$key.',"label":'.$label.',"values":{"type":"object","description":"One value for each column, under the `name` of the column","additionalProperties":'.$number.'}},"required":["key","label","values"]}}},"required":["columns","rows"],"title":"RestTableResult"}'],
            'progress' => ['RestProgressResult', '{"type":"object","properties":{"value":'.$number.',"target":{"type":"number"}},"required":["value","target"],"title":"RestProgressResult"}'],
        ];
    }

    private static function partition(int $max): string
    {
        $previous = '"previous":{"type":["number","null"],"description":"The value of the period before the range, present when the partition compares two periods"}';

        return '{"type":"object","properties":{"parts":{"type":"array","description":"The parts with the largest values, up to the limit of the metric","items":{"type":"object","properties":{"key":'.self::KEY.',"label":'.self::LABEL.',"value":'.self::NUMBER.','.$previous.'},"required":["key","label","value"]},"maxItems":'.$max.'},"other":{"type":["object","null"],"description":"The parts that the limit folds","properties":{"label":'.self::LABEL.',"value":'.self::NUMBER.','.$previous.'},"required":["label","value"]}},"required":["parts","other"]}';
    }

    private static function typed(string $type, string $result): string
    {
        return '{"allOf":[{"type":"object","properties":{"type":{"type":"string","const":"'.$type.'"}},"required":["type"]},'.$result.']}';
    }
}
