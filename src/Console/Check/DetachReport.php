<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Console\View\Components\Factory;

final readonly class DetachReport
{
    /**
     * @param  list<array{resource: string, relation: string}>  $detachable
     * @param  list<array{resource: string, relation: string, table: string}>  $skipped
     * @param  list<string>  $problems
     */
    public function __construct(
        private array $detachable = [],
        private array $skipped = [],
        private array $problems = [],
    ) {}

    public function withDetachable(string $resource, string $relation): self
    {
        return new self([...$this->detachable, ['resource' => $resource, 'relation' => $relation]], $this->skipped, $this->problems);
    }

    public function withSkipped(string $resource, string $relation, string $table): self
    {
        return new self($this->detachable, [...$this->skipped, ['resource' => $resource, 'relation' => $relation, 'table' => $table]], $this->problems);
    }

    public function withProblem(string $problem): self
    {
        return new self($this->detachable, $this->skipped, [...$this->problems, $problem]);
    }

    public function passed(): bool
    {
        return $this->problems === [];
    }

    /** @return array{relations: array{detachable: list<array{resource: string, relation: string}>, skipped: list<array{resource: string, relation: string, table: string}>, problems: list<string>}} */
    public function toArray(): array
    {
        return ['relations' => ['detachable' => $this->detachable, 'skipped' => $this->skipped, 'problems' => $this->problems]];
    }

    public function render(Factory $components): void
    {
        if ($this->problems === []) {
            $components->twoColumnDetail('relations', $this->summary());
        } else {
            $components->twoColumnDetail('relations', '<fg=red;options=bold>FAILED</>');
            $components->bulletList($this->problems);
        }

        foreach ($this->skipped as $skipped) {
            $components->twoColumnDetail("{$skipped['resource']} [{$skipped['relation']}]", "skipped, no {$skipped['table']} table");
        }
    }

    private function summary(): string
    {
        $count = count($this->detachable);
        $summary = match ($count) {
            0 => 'no relation accepts detach',
            1 => '1 relation accepts detach',
            default => "{$count} relations accept detach",
        };

        return $this->skipped === [] ? $summary : $summary.', '.count($this->skipped).' skipped';
    }
}
