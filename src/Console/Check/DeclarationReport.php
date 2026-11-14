<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Str;
use Lomkit\Rest\Actions\Action;

final readonly class DeclarationReport
{
    /**
     * @param  array<string, list<Action>>  $actions
     * @param  list<string>  $problems
     */
    public function __construct(
        private array $actions = [],
        private int $metrics = 0,
        private int $instructions = 0,
        private array $problems = [],
    ) {}

    public function withAction(string $resource, Action $action): self
    {
        return new self([...$this->actions, $resource => [...$this->actionsOf($resource), $action]], $this->metrics, $this->instructions, $this->problems);
    }

    public function withMetric(): self
    {
        return new self($this->actions, $this->metrics + 1, $this->instructions, $this->problems);
    }

    public function withInstruction(): self
    {
        return new self($this->actions, $this->metrics, $this->instructions + 1, $this->problems);
    }

    public function withProblem(string $problem): self
    {
        return new self($this->actions, $this->metrics, $this->instructions, [...$this->problems, $problem]);
    }

    /** @return list<Action> */
    public function actionsOf(string $resource): array
    {
        return $this->actions[$resource] ?? [];
    }

    public function passed(): bool
    {
        return $this->problems === [];
    }

    /** @return array{declarations: array{actions: int, metrics: int, instructions: int, problems: list<string>}} */
    public function toArray(): array
    {
        return ['declarations' => ['actions' => $this->actionCount(), 'metrics' => $this->metrics, 'instructions' => $this->instructions, 'problems' => $this->problems]];
    }

    public function render(Factory $components): void
    {
        if ($this->problems === []) {
            $components->twoColumnDetail('declarations', $this->summary());

            return;
        }

        $components->twoColumnDetail('declarations', '<fg=red;options=bold>FAILED</>');
        $components->bulletList($this->problems);
    }

    private function summary(): string
    {
        $actions = $this->actionCount();

        return "{$actions} ".Str::plural('action', $actions).", {$this->metrics} ".Str::plural('metric', $this->metrics).", {$this->instructions} ".Str::plural('instruction', $this->instructions).' built';
    }

    private function actionCount(): int
    {
        return array_sum(array_map(count(...), $this->actions));
    }
}
