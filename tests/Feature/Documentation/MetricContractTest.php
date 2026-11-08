<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MetricContractTest extends DocumentationTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-07-15 12:00:00');
        DB::table('shows')->insert([
            ['id' => 1, 'title' => 'Opening night', 'seats' => 300, 'featured' => true, 'starts_on' => '2026-07-01', 'starts_at' => '2026-07-01 20:00:00', 'status' => 'on_sale', 'level' => 3, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'title' => 'Closing night', 'seats' => 0, 'featured' => false, 'starts_on' => '2026-07-03', 'starts_at' => '2026-07-03 21:00:00', 'status' => 'planned', 'level' => 1, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 3, 'title' => 'Matinee', 'seats' => 120, 'featured' => false, 'starts_on' => '2026-06-20', 'starts_at' => '2026-06-20 15:00:00', 'status' => 'on_sale', 'level' => 2, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ]);
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
    }

    #[Test]
    public function it_sends_each_metric_example_of_the_document(): void
    {
        $document = $this->document();
        $validator = new OpenApiValidator;
        $checked = [];

        foreach ($document['paths'] as $path => $operations) {
            if (! str_contains($operations['post']['operationId'] ?? '', '.metric.')) {
                continue;
            }

            $example = $operations['post']['requestBody']['content']['application/json']['examples']['default']['value'];

            $this->assertSame([], $validator->requestErrors($document, $path, 'post', $example), $path);

            $response = $this->postJson('api'.$path, (array) $example);

            $response->assertOk();
            $this->assertSame([], $validator->responseErrors($document, $path, 'post', 200, (string) $response->getContent()), $path);
            $checked[] = $path;
        }

        $this->assertSame(['/shows/metrics/show-count', '/shows/metrics/shows-per-month', '/shows/metrics/shows-by-status', '/shows/metrics/shows-by-level', '/shows/metrics/seats-by-level', '/shows/metrics/top-stages', '/shows/metrics/seats-goal', '/shows/metrics/occupancy'], $checked);
    }

    #[Test]
    public function it_answers_a_split_trend_as_the_document_describes(): void
    {
        $response = $this->postJson('api/shows/metrics/shows-per-month', []);

        $response->assertOk();
        $this->assertSame('{"data":{"type":"trend","series":[{"label":"2026-05","value":0},{"label":"2026-06","value":1},{"label":"2026-07","value":2}],"splits":[{"key":"on_sale","label":"OnSale","series":[{"label":"2026-05","value":0},{"label":"2026-06","value":1},{"label":"2026-07","value":1}]}],"other":{"label":"Other","series":[{"label":"2026-05","value":0},{"label":"2026-06","value":0},{"label":"2026-07","value":1}]}}}', (string) $response->getContent());
        $this->assertSame([], (new OpenApiValidator)->responseErrors($this->document(), '/shows/metrics/shows-per-month', 'post', 200, (string) $response->getContent()));
    }

    #[Test]
    public function it_answers_a_partition_compared_with_the_previous_period_as_the_document_describes(): void
    {
        DB::table('shows')->insert(['id' => 4, 'title' => 'Preview', 'seats' => 80, 'featured' => false, 'starts_on' => '2026-06-01', 'starts_at' => '2026-06-01 18:00:00', 'status' => 'planned', 'level' => 1, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
        $document = $this->document();
        $partition = $document['components']['schemas']['RestPartitionResult']['properties'];

        $response = $this->postJson('api/shows/metrics/shows-by-level', []);

        $response->assertOk();
        $this->assertSame('{"data":{"type":"partition","parts":[{"key":1,"label":"Open","value":1,"previous":1},{"key":2,"label":"Main","value":1,"previous":null}],"other":{"label":"Other","value":1,"previous":null}}}', (string) $response->getContent());
        $this->assertSame([], (new OpenApiValidator)->responseErrors($document, '/shows/metrics/shows-by-level', 'post', 200, (string) $response->getContent()));
        $this->assertSame(array_keys($partition['parts']['items']['properties']), array_keys($response->json('data.parts.0')));
        $this->assertSame(array_keys($partition['other']['properties']), array_keys($response->json('data.other')));
    }

    #[Test]
    public function it_answers_a_partition_of_percentiles_compared_with_the_previous_period_as_the_document_describes(): void
    {
        DB::table('shows')->insert(['id' => 4, 'title' => 'Preview', 'seats' => 80, 'featured' => false, 'starts_on' => '2026-06-01', 'starts_at' => '2026-06-01 18:00:00', 'status' => 'planned', 'level' => 1, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
        $document = $this->document();
        $part = $document['components']['schemas']['RestPartitionResult']['properties']['parts']['items']['properties'];

        $response = $this->postJson('api/shows/metrics/seats-by-level', []);

        $response->assertOk();
        $this->assertSame('{"data":{"type":"partition","parts":[{"key":3,"label":"Headline","value":300.0,"previous":null},{"key":2,"label":"Main","value":120.0,"previous":null},{"key":1,"label":"Open","value":0.0,"previous":80.0}],"other":null}}', (string) $response->getContent());
        $this->assertSame([], (new OpenApiValidator)->responseErrors($document, '/shows/metrics/seats-by-level', 'post', 200, (string) $response->getContent()));
        $this->assertSame(array_keys($part), array_keys($response->json('data.parts.0')));
    }

    #[Test]
    public function it_answers_a_table_with_a_median_and_a_percentile_as_the_document_describes(): void
    {
        $document = $this->document();
        $column = $document['components']['schemas']['RestTableResult']['properties']['columns']['items']['properties'];

        $response = $this->postJson('api/shows/metrics/top-stages', []);

        $response->assertOk();
        $this->assertSame('{"data":{"type":"table","columns":[{"name":"shows","label":"Shows","aggregate":"count"},{"name":"seats","label":"Seats","aggregate":"sum"},{"name":"median","label":"Median","aggregate":"median"},{"name":"p90","label":"P90","aggregate":"percentile","percentile":0.9}],"rows":[{"key":"on_sale","label":"OnSale","values":{"shows":2,"seats":420,"median":210.0,"p90":282.0}},{"key":"planned","label":"Planned","values":{"shows":1,"seats":0,"median":0.0,"p90":0.0}}]}}', (string) $response->getContent());
        $this->assertSame([], (new OpenApiValidator)->responseErrors($document, '/shows/metrics/top-stages', 'post', 200, (string) $response->getContent()));
        $this->assertSame(array_keys($column), array_keys($response->json('data.columns.3')));
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('acceptedMetrics')]
    public function it_answers_a_metric_that_the_document_describes(string $path, array $body): void
    {
        $document = $this->document();
        $validator = new OpenApiValidator;

        $this->assertSame([], $validator->requestErrors($document, $path, 'post', $body));

        $response = $this->postJson('api'.$path, $body);

        $response->assertOk();
        $this->assertSame([], $validator->responseErrors($document, $path, 'post', 200, (string) $response->getContent()));
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function acceptedMetrics(): array
    {
        return [
            'another range' => ['/shows/metrics/show-count', ['range' => 90]],
            'another range of a trend in a time zone' => ['/shows/metrics/shows-per-month', ['range' => 12, 'timezone' => 'Europe/Paris']],
            'a filter' => ['/shows/metrics/shows-by-status', ['search' => ['filters' => [['field' => 'featured', 'value' => true]]]]],
            'a text and a scope' => ['/shows/metrics/top-stages', ['search' => ['text' => ['value' => 'night'], 'scopes' => [['name' => 'startingFrom', 'parameters' => ['2026-07-01']]]]]],
            'the trashed rows' => ['/shows/metrics/seats-goal', ['search' => ['trashed' => 'with']]],
            'an instruction' => ['/shows/metrics/occupancy', ['search' => ['instructions' => [['name' => 'only-featured']]]]],
        ];
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('refusedMetrics')]
    public function it_refuses_a_metric_that_the_document_does_not_describe(string $path, array $body): void
    {
        $this->assertNotSame([], (new OpenApiValidator)->requestErrors($this->document(), $path, 'post', $body));

        $this->postJson('api'.$path, $body)->assertUnprocessable();
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function refusedMetrics(): array
    {
        return [
            'a range that the metric does not declare' => ['/shows/metrics/show-count', ['range' => 7]],
            'a range of another type' => ['/shows/metrics/show-count', ['range' => 'month']],
            'a range on a metric without ranges' => ['/shows/metrics/shows-by-status', ['range' => 30]],
            'a time zone of another type' => ['/shows/metrics/shows-per-month', ['timezone' => 1]],
            'a filter on a field that the search does not know' => ['/shows/metrics/top-stages', ['search' => ['filters' => [['field' => 'secret', 'value' => 'backstage']]]]],
            'an unknown key in a filter' => ['/shows/metrics/occupancy', ['search' => ['filters' => [['field' => 'title', 'value' => 'Matinee', 'label' => 'first']]]]],
        ];
    }
}
