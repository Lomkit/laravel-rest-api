<?php

namespace Lomkit\Rest\Scramble\Metrics;

use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\MetricRange;
use Lomkit\Rest\Metrics\ResultSchemas;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Search\SearchSchema;
use Lomkit\Rest\Scramble\Types\Nothing;
use Lomkit\Rest\Validation\Search\SearchShape;

final readonly class MetricSchema
{
    public function __construct(
        private ComponentRegistry $components,
    ) {}

    public function body(Resource $resource, Metric $metric): ObjectType
    {
        return Schemas::required([
            'search' => (new SearchSchema($this->components))->reference($resource, SearchShape::Aggregate),
            'range' => self::range($metric),
            'timezone' => (new StringType)->setDescription('The time zone of the periods of the metric, such as `Europe/Paris`; the time zone of the application when the request sends none'),
        ], ['search', 'range', 'timezone'], 'The metric runs on the rows that `search` matches, every row without `search`');
    }

    public function response(Metric $metric): ObjectType
    {
        $schema = $metric->schema();
        $type = Schemas::required(['type' => (new StringType)->const($metric->type())], [], $schema === null ? 'The other keys of the result are not documented' : '');

        if ($schema === null) {
            return Schemas::required(['data' => $type]);
        }

        $data = new AllOf;
        $data->setItems([$type, $this->result($schema)]);

        return Schemas::required(['data' => $data]);
    }

    /** @return array<string, int|string> */
    public function example(Metric $metric): array
    {
        $values = self::values($metric);

        if ($values === []) {
            return ['timezone' => 'UTC'];
        }

        return ['range' => $values[0], 'timezone' => 'UTC'];
    }

    private function result(Type $schema): Type
    {
        $json = json_encode($schema->serializeAs31());

        foreach (self::results() as $name => $result) {
            if (json_encode($result->serializeAs31()) === $json) {
                return $this->components->schema($name, self::class, fn (): Type => $result);
            }
        }

        return $schema;
    }

    private static function range(Metric $metric): Type
    {
        $values = self::values($metric);

        if ($values === []) {
            return new Nothing;
        }

        return (new IntegerType)->enum($values)->setDescription("The range of the metric: `{$values[0]}` when the request sends none");
    }

    /** @return list<int> */
    private static function values(Metric $metric): array
    {
        return array_map(fn (MetricRange $range): int => $range->value, MetricRange::listOf($metric->ranges(), $metric::class));
    }

    /** @return array<string, ObjectType> */
    private static function results(): array
    {
        return [
            'RestValueResult' => ResultSchemas::value(),
            'RestTrendResult' => ResultSchemas::trend(),
            'RestPartitionResult' => ResultSchemas::partition(),
            'RestTableResult' => ResultSchemas::table(),
            'RestProgressResult' => ResultSchemas::progress(),
        ];
    }
}
