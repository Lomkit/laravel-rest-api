<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Str;

final readonly class PerimeterReport
{
    /**
     * @param  list<string>  $perimeter
     * @param  list<string>  $withoutPerimeter
     * @param  list<string>  $rowProblems
     * @param  list<array{resource: string, action: string, ability: string}>  $checkedActions
     * @param  list<string>  $actionProblems
     */
    public function __construct(
        private array $perimeter = [],
        private array $withoutPerimeter = [],
        private array $rowProblems = [],
        private array $checkedActions = [],
        private array $actionProblems = [],
    ) {}

    public function withPerimeter(string $resource): self
    {
        return new self([...$this->perimeter, $resource], $this->withoutPerimeter, $this->rowProblems, $this->checkedActions, $this->actionProblems);
    }

    public function withoutPerimeter(string $resource): self
    {
        return new self($this->perimeter, [...$this->withoutPerimeter, $resource], $this->rowProblems, $this->checkedActions, $this->actionProblems);
    }

    public function withRowProblem(string $problem): self
    {
        return new self($this->perimeter, $this->withoutPerimeter, [...$this->rowProblems, $problem], $this->checkedActions, $this->actionProblems);
    }

    public function withCheckedAction(string $resource, string $action, string $ability): self
    {
        $checked = [...$this->checkedActions, ['resource' => $resource, 'action' => $action, 'ability' => $ability]];

        return new self($this->perimeter, $this->withoutPerimeter, $this->rowProblems, $checked, $this->actionProblems);
    }

    public function withActionProblem(string $problem): self
    {
        return new self($this->perimeter, $this->withoutPerimeter, $this->rowProblems, $this->checkedActions, [...$this->actionProblems, $problem]);
    }

    public function passed(): bool
    {
        return $this->rowProblems === [] && $this->actionProblems === [];
    }

    /** @return array{rows: array{perimeter: list<string>, without_perimeter: list<string>, problems: list<string>}, actions: array{checked: list<array{resource: string, action: string, ability: string}>, problems: list<string>}} */
    public function toArray(): array
    {
        return [
            'rows' => ['perimeter' => $this->perimeter, 'without_perimeter' => $this->withoutPerimeter, 'problems' => $this->rowProblems],
            'actions' => ['checked' => $this->checkedActions, 'problems' => $this->actionProblems],
        ];
    }

    public function render(Factory $components): void
    {
        self::section($components, 'rows', $this->rowsSummary(), $this->rowProblems);

        foreach ($this->withoutPerimeter as $resource) {
            $components->twoColumnDetail($resource, '#[WithoutPerimeter]');
        }

        self::section($components, 'actions', $this->actionsSummary(), $this->actionProblems);
    }

    /** @param list<string> $problems */
    private static function section(Factory $components, string $label, string $summary, array $problems): void
    {
        if ($problems === []) {
            $components->twoColumnDetail($label, $summary);

            return;
        }

        $components->twoColumnDetail($label, '<fg=red;options=bold>FAILED</>');
        $components->bulletList($problems);
    }

    private function rowsSummary(): string
    {
        $count = count($this->perimeter);
        $summary = "{$count} ".Str::plural('resource', $count).' with a perimeter';

        return $this->withoutPerimeter === [] ? $summary : $summary.', '.count($this->withoutPerimeter).' marked #[WithoutPerimeter]';
    }

    private function actionsSummary(): string
    {
        $count = count($this->checkedActions);

        return match ($count) {
            0 => 'no action runs on rows',
            1 => '1 action checks each row',
            default => "{$count} actions check each row",
        };
    }
}
