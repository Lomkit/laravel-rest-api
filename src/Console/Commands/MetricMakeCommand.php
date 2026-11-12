<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Support\Str;
use Lomkit\Rest\Console\Generation\MetricType;
use Lomkit\Rest\Console\Generation\Stub;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'rest:metric', description: 'Create a new metric class')]
class MetricMakeCommand extends RestGeneratorCommand
{
    private const string FIELD_NAME = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    protected $type = 'Metric';

    private MetricType $metricType = MetricType::Value;

    private string $groupBy = '';

    #[\Override]
    protected function stub(): Stub
    {
        return $this->metricType->stub();
    }

    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        if ($input->getOption('type') === null) {
            $input->setOption('type', select('Which type of metric?', MetricType::options(), MetricType::Value->value));
        }

        $type = $input->getOption('type');

        if (! is_string($type) || MetricType::tryFrom($type)?->groups() !== true || $input->getOption('group-by') !== null) {
            return;
        }

        $input->setOption('group-by', text('Which field should the metric group by?'));
    }

    #[\Override]
    protected function prepare(): void
    {
        $type = $this->option('type');
        $type = is_string($type) ? $type : MetricType::Value->value;
        $this->metricType = MetricType::tryFrom($type) ?? $this->fail("The type [{$type}] is not a metric type: use ".MetricType::list().'.');

        $groupBy = $this->option('group-by');
        $groupBy = is_string($groupBy) ? $groupBy : '';

        if (! $this->metricType->groups()) {
            $this->warnAboutAnUnusedField($groupBy);

            return;
        }

        if ($groupBy === '') {
            $this->fail("A {$this->metricType->value} metric groups by a field: pass --group-by.");
        }

        if (preg_match(self::FIELD_NAME, $groupBy) !== 1) {
            $this->fail("The field [{$groupBy}] is not a field name: use letters, digits and underscores, starting with a letter or an underscore.");
        }

        $this->groupBy = $groupBy;
    }

    #[\Override]
    protected function replacements(string $name): array
    {
        return match ($this->metricType) {
            MetricType::Partition, MetricType::Table => ['{{ groupBy }}' => $this->groupBy],
            MetricType::Custom => ['{{ type }}' => Str::slug(Str::kebab(class_basename($name)))],
            default => [],
        };
    }

    #[\Override]
    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            ['type', null, InputOption::VALUE_REQUIRED, 'The type of the metric: '.MetricType::list()],
            ['group-by', null, InputOption::VALUE_REQUIRED, 'The field that a partition or a table groups by'],
        ];
    }

    #[\Override]
    protected function afterWritten(): void
    {
        $this->components->info('List it in the metrics() of a resource by its class: '.class_basename($this->qualifyClass($this->getNameInput())).'::class.');
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Rest\Metrics';
    }

    private function warnAboutAnUnusedField(string $groupBy): void
    {
        if ($groupBy === '') {
            return;
        }

        $this->components->warn("A {$this->metricType->value} metric does not group by a field: --group-by is ignored.");
    }
}
