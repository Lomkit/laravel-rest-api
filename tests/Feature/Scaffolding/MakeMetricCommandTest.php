<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MakeMetricCommandTest extends ScaffoldingTestCase
{
    private const string TYPE_QUESTION = 'Which type of metric?';

    private const string FIELD_QUESTION = 'Which field should the metric group by?';

    private const array TYPES = [
        'value' => 'Value: one number',
        'trend' => 'Trend: a series by day, week or month',
        'partition' => 'Partition: one total per group',
        'progress' => 'Progress: a number and its target',
        'table' => 'Table: the top groups with their totals',
        'custom' => 'Custom: a type and a result of your own',
    ];

    private const string VALUE = <<<'PHP'
        <?php

        namespace App\Rest\Metrics;

        use Illuminate\Database\Eloquent\Builder;
        use Lomkit\Rest\Metrics\Value as RestValue;
        use Lomkit\Rest\Metrics\ValueResult;

        class OrderCount extends RestValue
        {
            #[\Override]
            public function calculate(Builder $query): ValueResult
            {
                return $this->count($query);
            }
        }

        PHP;

    private const string TREND = <<<'PHP'
        <?php

        namespace App\Rest\Metrics;

        use Illuminate\Database\Eloquent\Builder;
        use Lomkit\Rest\Metrics\Trend as RestTrend;
        use Lomkit\Rest\Metrics\TrendResult;

        class OrdersPerDay extends RestTrend
        {
            #[\Override]
            public function calculate(Builder $query): TrendResult
            {
                return $this->countByDays($query);
            }

            #[\Override]
            public function ranges(): array
            {
                return [
                    7 => '7 days',
                    30 => '30 days',
                    90 => '90 days',
                ];
            }
        }

        PHP;

    private const string PARTITION = <<<'PHP'
        <?php

        namespace App\Rest\Metrics;

        use Illuminate\Database\Eloquent\Builder;
        use Lomkit\Rest\Metrics\Partition as RestPartition;
        use Lomkit\Rest\Metrics\PartitionResult;

        class OrdersByStatus extends RestPartition
        {
            #[\Override]
            public function calculate(Builder $query): PartitionResult
            {
                return $this->count($query, 'status');
            }
        }

        PHP;

    private const string PROGRESS = <<<'PHP'
        <?php

        namespace App\Rest\Metrics;

        use Illuminate\Database\Eloquent\Builder;
        use Lomkit\Rest\Metrics\Progress as RestProgress;
        use Lomkit\Rest\Metrics\ProgressResult;

        class OrderGoal extends RestProgress
        {
            #[\Override]
            public function calculate(Builder $query): ProgressResult
            {
                return $this->count($query, target: 100);
            }
        }

        PHP;

    private const string TABLE = <<<'PHP'
        <?php

        namespace App\Rest\Metrics;

        use Illuminate\Database\Eloquent\Builder;
        use Lomkit\Rest\Metrics\Table as RestTable;
        use Lomkit\Rest\Metrics\TableColumn;
        use Lomkit\Rest\Metrics\TableResult;

        class TopChannels extends RestTable
        {
            #[\Override]
            public function calculate(Builder $query): TableResult
            {
                return $this->rows($query, 'channel', [
                    'count' => TableColumn::count(),
                ]);
            }
        }

        PHP;

    private const string CUSTOM = <<<'PHP'
        <?php

        namespace App\Rest\Metrics;

        use Illuminate\Database\Eloquent\Builder;
        use Lomkit\Rest\Metrics\Metric as RestMetric;
        use Lomkit\Rest\Metrics\ValueResult;

        class OrderBacklog extends RestMetric
        {
            #[\Override]
            public function type(): string
            {
                return 'order-backlog';
            }

            #[\Override]
            public function calculate(Builder $query): ValueResult
            {
                return new ValueResult($query->count());
            }
        }

        PHP;

    /** @param array<string, string> $options */
    #[Test]
    #[DataProvider('types')]
    public function it_writes_a_metric_of_each_type(string $name, array $options, string $expected): void
    {
        $this->artisan('rest:metric', ['name' => $name, ...$options, '--no-interaction' => true])
            ->expectsOutputToContain("Metric [app/Rest/Metrics/{$name}.php] created successfully.")
            ->expectsOutputToContain("List it in the metrics() of a resource by its class: {$name}::class.")
            ->assertSuccessful();

        $this->assertSame($expected, $this->metric($name));
    }

    /** @return array<string, array{string, array<string, string>, string}> */
    public static function types(): array
    {
        return [
            'value' => ['OrderCount', ['--type' => 'value'], self::VALUE],
            'trend' => ['OrdersPerDay', ['--type' => 'trend'], self::TREND],
            'partition' => ['OrdersByStatus', ['--type' => 'partition', '--group-by' => 'status'], self::PARTITION],
            'progress' => ['OrderGoal', ['--type' => 'progress'], self::PROGRESS],
            'table' => ['TopChannels', ['--type' => 'table', '--group-by' => 'channel'], self::TABLE],
            'custom' => ['OrderBacklog', ['--type' => 'custom'], self::CUSTOM],
        ];
    }

    #[Test]
    public function it_writes_a_value_metric_when_no_type_is_given_without_interaction(): void
    {
        $this->artisan('rest:metric', ['name' => 'OrderCount', '--no-interaction' => true])->assertSuccessful();

        $this->assertSame(self::VALUE, $this->metric('OrderCount'));
    }

    #[Test]
    #[DataProvider('customNames')]
    public function it_names_the_type_of_a_custom_metric_after_its_class(string $name, string $namespace, string $type): void
    {
        $this->artisan('rest:metric', ['name' => $name, '--type' => 'custom', '--no-interaction' => true])->assertSuccessful();

        $this->assertStringContainsString("namespace {$namespace};\n", $this->metric($name));
        $this->assertStringContainsString("        return '{$type}';\n", $this->metric($name));
    }

    /** @return array<string, array{string, string, string}> */
    public static function customNames(): array
    {
        return [
            'nested class' => ['Admin/LateTicketsMetric', 'App\Rest\Metrics\Admin', 'late-tickets-metric'],
            'underscore' => ['Late_Tickets', 'App\Rest\Metrics', 'late-tickets'],
        ];
    }

    #[Test]
    public function it_asks_for_the_type_and_the_field(): void
    {
        $this->artisan('rest:metric', ['name' => 'OrdersByStatus'])
            ->expectsChoice(self::TYPE_QUESTION, 'partition', self::TYPES)
            ->expectsQuestion(self::FIELD_QUESTION, 'status')
            ->assertSuccessful();

        $this->assertSame(self::PARTITION, $this->metric('OrdersByStatus'));
    }

    #[Test]
    public function it_writes_a_value_metric_when_the_type_question_is_answered_with_its_default(): void
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        fwrite($stream, "\n");
        rewind($stream);
        $input = new ArrayInput(['name' => 'OrderCount']);
        $input->setStream($stream);

        $exitCode = Artisan::all()['rest:metric']->run($input, $output = new BufferedOutput);

        $this->assertSame(0, $exitCode, $output->fetch());
        $this->assertSame(self::VALUE, $this->metric('OrderCount'));
    }

    #[Test]
    public function it_asks_for_the_name_first(): void
    {
        $this->artisan('rest:metric')
            ->expectsQuestion('What should the metric be named?', 'TopChannels')
            ->expectsChoice(self::TYPE_QUESTION, 'table', self::TYPES)
            ->expectsQuestion(self::FIELD_QUESTION, 'channel')
            ->assertSuccessful();

        $this->assertSame(self::TABLE, $this->metric('TopChannels'));
    }

    #[Test]
    public function it_asks_no_field_for_a_type_that_does_not_group(): void
    {
        $this->artisan('rest:metric', ['name' => 'OrdersPerDay'])
            ->expectsChoice(self::TYPE_QUESTION, 'trend', self::TYPES)
            ->doesntExpectOutputToContain(self::FIELD_QUESTION)
            ->assertSuccessful();

        $this->assertSame(self::TREND, $this->metric('OrdersPerDay'));
    }

    #[Test]
    public function it_asks_only_for_the_field_when_the_type_is_given(): void
    {
        $this->artisan('rest:metric', ['name' => 'TopChannels', '--type' => 'table'])
            ->expectsQuestion(self::FIELD_QUESTION, 'channel')
            ->doesntExpectOutputToContain(self::TYPE_QUESTION)
            ->assertSuccessful();

        $this->assertSame(self::TABLE, $this->metric('TopChannels'));
    }

    #[Test]
    public function it_fails_after_an_empty_field_answer(): void
    {
        $this->artisan('rest:metric', ['name' => 'Unanswered', '--type' => 'partition'])
            ->expectsQuestion(self::FIELD_QUESTION, '')
            ->expectsOutputToContain('A partition metric groups by a field: pass --group-by.')
            ->assertFailed();

        $this->assertFileDoesNotExist(app_path('Rest/Metrics/Unanswered.php'));
    }

    #[Test]
    public function it_asks_nothing_when_the_options_are_given(): void
    {
        $this->artisan('rest:metric', ['name' => 'OrdersByStatus', '--type' => 'partition', '--group-by' => 'status'])
            ->doesntExpectOutputToContain(self::TYPE_QUESTION)
            ->doesntExpectOutputToContain(self::FIELD_QUESTION)
            ->assertSuccessful();

        $this->assertSame(self::PARTITION, $this->metric('OrdersByStatus'));
    }

    #[Test]
    #[DataProvider('groupingTypes')]
    public function it_fails_without_a_field_for_a_type_that_groups(string $type): void
    {
        $this->artisan('rest:metric', ['name' => 'Ungrouped', '--type' => $type, '--no-interaction' => true])
            ->expectsOutputToContain("A {$type} metric groups by a field: pass --group-by.")
            ->assertFailed();

        $this->assertFileDoesNotExist(app_path('Rest/Metrics/Ungrouped.php'));
    }

    /** @return array<string, array{string}> */
    public static function groupingTypes(): array
    {
        return [
            'partition' => ['partition'],
            'table' => ['table'],
        ];
    }

    #[Test]
    public function it_fails_for_an_unknown_type(): void
    {
        $this->artisan('rest:metric', ['name' => 'Gauge', '--type' => 'gauge', '--no-interaction' => true])
            ->expectsOutputToContain('The type [gauge] is not a metric type: use value, trend, partition, progress, table or custom.')
            ->assertFailed();

        $this->assertFileDoesNotExist(app_path('Rest/Metrics/Gauge.php'));
    }

    #[Test]
    public function it_fails_for_a_field_that_is_not_a_field_name(): void
    {
        $this->artisan('rest:metric', ['name' => 'Quoted', '--type' => 'partition', '--group-by' => "status'", '--no-interaction' => true])
            ->expectsOutputToContain("The field [status'] is not a field name: use letters, digits and underscores, starting with a letter or an underscore.")
            ->assertFailed();

        $this->assertFileDoesNotExist(app_path('Rest/Metrics/Quoted.php'));
    }

    #[Test]
    public function it_ignores_a_field_for_a_type_that_does_not_group(): void
    {
        $this->artisan('rest:metric', ['name' => 'OrderGoal', '--type' => 'progress', '--group-by' => 'status', '--no-interaction' => true])
            ->expectsOutputToContain('A progress metric does not group by a field: --group-by is ignored.')
            ->assertSuccessful();

        $this->assertSame(self::PROGRESS, $this->metric('OrderGoal'));
    }

    private function metric(string $name): string
    {
        return (string) file_get_contents(app_path("Rest/Metrics/{$name}.php"));
    }
}
