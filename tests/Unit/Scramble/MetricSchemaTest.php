<?php

namespace Lomkit\Rest\Tests\Unit\Scramble;

use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\MetricResult;
use Lomkit\Rest\Metrics\Partition;
use Lomkit\Rest\Metrics\Progress;
use Lomkit\Rest\Metrics\ProgressResult;
use Lomkit\Rest\Metrics\ResultSchemas;
use Lomkit\Rest\Metrics\Table;
use Lomkit\Rest\Metrics\Trend;
use Lomkit\Rest\Metrics\Value;
use Lomkit\Rest\Metrics\ValueResult;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Metrics\MetricSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MetricSchemaTest extends TestCase
{
    /** @param list<string> $components */
    #[Test]
    #[DataProvider('metrics')]
    public function it_documents_the_result_of_a_metric_from_its_schema(Metric $metric, string $data, array $components): void
    {
        $registry = new Components;
        $response = (new MetricSchema(new ComponentRegistry($registry)))->response($metric);

        $this->assertSame('{"type":"object","properties":{"data":'.$data.'},"required":["data"]}', json_encode($response->serializeAs31()));
        $this->assertSame($components, array_keys($registry->schemas));
    }

    /** @return array<string, array{Metric, string, list<string>}> */
    public static function metrics(): array
    {
        return [
            'a type of the application without a schema' => [new class extends Metric
            {
                #[\Override]
                public function type(): string
                {
                    return 'gauge';
                }

                #[\Override]
                public function calculate(Builder $query): MetricResult
                {
                    return new ValueResult(1);
                }
            }, '{"type":"object","description":"The other keys of the result are not documented","properties":{"type":{"type":"string","const":"gauge"}},"required":["type"]}', []],
            'a built-in type under another name' => [new class extends Value
            {
                #[\Override]
                public function type(): string
                {
                    return 'counter';
                }

                #[\Override]
                public function calculate(Builder $query): ValueResult
                {
                    return new ValueResult(1);
                }
            }, '{"allOf":[{"type":"object","properties":{"type":{"type":"string","const":"counter"}},"required":["type"]},{"$ref":"#\/components\/schemas\/RestValueResult"}]}', ['RestValueResult']],
            'a built-in type that changes its result' => [new class extends Progress
            {
                #[\Override]
                public function calculate(Builder $query): ProgressResult
                {
                    return new ProgressResult(1, 2);
                }

                #[\Override]
                public function schema(): ?Type
                {
                    $schema = ResultSchemas::progress();
                    $schema->addProperty('goal', new NumberType);

                    return $schema;
                }
            }, '{"allOf":[{"type":"object","properties":{"type":{"type":"string","const":"progress"}},"required":["type"]},{"type":"object","properties":{"value":{"type":["number","null"]},"target":{"type":"number"},"goal":{"type":"number"}},"required":["value","target"]}]}', []],
            'a type of the application with a built-in result' => [new class extends Metric
            {
                #[\Override]
                public function type(): string
                {
                    return 'goal';
                }

                #[\Override]
                public function calculate(Builder $query): MetricResult
                {
                    return new ProgressResult(1, 2);
                }

                #[\Override]
                public function schema(): ?Type
                {
                    return ResultSchemas::progress();
                }
            }, '{"allOf":[{"type":"object","properties":{"type":{"type":"string","const":"goal"}},"required":["type"]},{"$ref":"#\/components\/schemas\/RestProgressResult"}]}', ['RestProgressResult']],
        ];
    }

    /** @param class-string<Metric> $class */
    #[Test]
    #[DataProvider('types')]
    public function it_declares_the_schema_of_each_built_in_type_with_the_return_type_of_a_metric(string $class): void
    {
        $this->assertSame('?'.Type::class, (string) (new ReflectionMethod($class, 'schema'))->getReturnType());
    }

    /** @return array<string, array{class-string<Metric>}> */
    public static function types(): array
    {
        return [
            'metric' => [Metric::class],
            'value' => [Value::class],
            'trend' => [Trend::class],
            'partition' => [Partition::class],
            'table' => [Table::class],
            'progress' => [Progress::class],
        ];
    }
}
